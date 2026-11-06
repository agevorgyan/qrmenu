<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Supplier;
use App\Models\SupplierIngredient;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ArmenianTaxInvoiceService
{
    /**
     * Map localized Armenian units to canonical unit codes.
     *
     * @var array<string, string>
     */
    protected array $unitMap = [
        'կգ' => 'kg',
        'կիլոգրամ' => 'kg',
        'kg' => 'kg',
        'գ' => 'g',
        'գր' => 'g',
        'գրամ' => 'g',
        'g' => 'g',
        'լ' => 'l',
        'լիտր' => 'l',
        'l' => 'l',
        'մլ' => 'ml',
        'միլիլիտր' => 'ml',
        'ml' => 'ml',
        'հատ' => 'piece',
        'շտ' => 'piece',
        'piece' => 'piece',
        'փաթեթ' => 'pack',
        'տուփ' => 'pack',
        'պակետ' => 'pack',
        'pack' => 'pack',
        'շիշ' => 'bottle',
        'bottle' => 'bottle',
        'արկղ' => 'box',
        'box' => 'box',
        'բաժին' => 'portion',
        'պորցիա' => 'portion',
        'տոննա' => 'ton',
        'տ' => 'ton',
    ];

    public function __construct(
        protected InventoryLedgerService $ledgerService
    ) {}

    /**
     * Parse uploaded Armenian e-invoicing XML or XLSX file.
     *
     * @return array{
     *     type: string,
     *     series: ?string,
     *     number: ?string,
     *     invoice_number: string,
     *     supply_date: ?string,
     *     additional_data: ?string,
     *     supplier: array{tin: string, name: string, vat_number: ?string, address: ?string, bank_name: ?string, bank_account: ?string, supply_location: ?string},
     *     buyer: array{tin: string, name: string, delivery_location: ?string},
     *     items: array<int, array{description: string, classifier_code: ?string, unit_name: string, quantity: float, unit_price: float, price: float, vat_rate: float, vat_amount: float, total_price: float}>,
     *     totals: array{price: float, vat: float, total_price: float, environmental_fee: float, tare_price: float},
     *     raw_xml?: string
     * }
     */
    public function parseFile(UploadedFile|string $file): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $originalName = $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($ext === 'xml') {
            $content = file_get_contents($filePath);

            return $this->parseXml($content);
        }

        if (in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            return $this->parseXlsx($filePath);
        }

        throw new InvalidArgumentException(__('Unsupported invoice format. Please upload an XML (.xml) or Excel (.xlsx) file.'));
    }

    /**
     * Parse Armenian e-Invoicing XML.
     *
     * @return array<string, mixed>
     */
    public function parseXml(string $content): array
    {
        if (trim($content) === '') {
            throw new InvalidArgumentException(__('The uploaded XML file is empty.'));
        }

        // Clean XML namespaces to simplify SimpleXML traversal
        $xmlClean = preg_replace('/xmlns=[\x27\x22][^\x27\x22]+[\x27\x22]/', '', $content);
        $xmlClean = preg_replace('/xmlns:[a-zA-Z0-9_-]+[\x27\x22][^\x27\x22]+[\x27\x22]/', '', $xmlClean);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlClean);

        if (! $xml) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $msg = ! empty($errors) ? $errors[0]->message : 'Invalid XML';
            throw new InvalidArgumentException(__('Failed to parse XML: :error', ['error' => $msg]));
        }

        // Locate SignableData
        $signable = $xml->SignedData->Data->SignableData ?? $xml->SignableData ?? $xml;

        // General Info
        $series = (string) ($signable->GeneralInfo->InvoiceNumber->Series ?? '');
        $num = (string) ($signable->GeneralInfo->InvoiceNumber->Number ?? '');
        $invoiceNumber = $series.$num;

        if ($invoiceNumber === '') {
            $invoiceNumber = 'INV-'.strtoupper(Str::random(8));
        }

        $supplyDateRaw = (string) ($signable->GeneralInfo->SupplyDate ?? '');
        $supplyDate = null;
        if ($supplyDateRaw !== '') {
            try {
                $supplyDate = Carbon::parse($supplyDateRaw)->toDateString();
            } catch (\Throwable) {
                $supplyDate = now()->toDateString();
            }
        }

        $additionalData = (string) ($signable->GeneralInfo->AdditionalData ?? '');

        // Supplier Info
        $supplierTin = (string) ($signable->SupplierInfo->Taxpayer->TIN ?? $signable->SupplierInfo->TIN ?? '');
        $supplierName = (string) ($signable->SupplierInfo->Taxpayer->Name ?? $signable->SupplierInfo->Name ?? '');
        $supplierVat = (string) ($signable->SupplierInfo->VATNumber ?? '');
        $supplierAddress = (string) ($signable->SupplierInfo->Taxpayer->Address ?? '');
        $bankName = (string) ($signable->SupplierInfo->Taxpayer->BankAccount->BankName ?? '');
        $bankAccount = (string) ($signable->SupplierInfo->Taxpayer->BankAccount->BankAccountNumber ?? '');
        $supplyLocation = (string) ($signable->SupplierInfo->SupplyLocation ?? '');

        // Buyer Info
        $buyerTin = (string) ($signable->BuyerInfo->Taxpayer->TIN ?? '');
        $buyerName = (string) ($signable->BuyerInfo->Taxpayer->Name ?? '');
        $deliveryLocation = (string) ($signable->BuyerInfo->DeliveryLocation ?? '');

        // Line Items
        $items = [];
        if (isset($signable->GoodsInfo->Good)) {
            foreach ($signable->GoodsInfo->Good as $good) {
                $qty = (float) ($good->Amount ?? 0);
                $totalPrice = (float) ($good->TotalPrice ?? 0);
                // Unit price including VAT: total price divided by quantity
                $unitPriceGross = ($qty > 0 && $totalPrice > 0)
                    ? round($totalPrice / $qty, 4)
                    : (float) ($good->PricePerUnit ?? 0);

                $items[] = [
                    'description' => trim((string) ($good->Description ?? '')),
                    'classifier_code' => trim((string) ($good->ClassifierCode ?? '')),
                    'unit_name' => trim((string) ($good->Unit ?? '')),
                    'quantity' => $qty,
                    'unit_price' => $unitPriceGross,
                    'price' => (float) ($good->Price ?? 0),
                    'vat_rate' => (float) ($good->VATRate ?? 0),
                    'vat_amount' => (float) ($good->VAT ?? 0),
                    'total_price' => $totalPrice,
                ];
            }
        }

        // Totals
        $rawTotal = $signable->GoodsInfo->Total ?? null;
        $totalVat = (float) ($rawTotal->TotalVAT ?? $rawTotal->VAT ?? 0);
        $totalPriceWithoutVat = (float) ($rawTotal->TotalPriceWithoutVAT ?? $rawTotal->Price ?? 0);
        $totalPrice = (float) ($rawTotal->TotalPrice ?? 0);
        $envFee = (float) ($rawTotal->TotalEnvironmentalFee ?? $rawTotal->EnvironmentalFee ?? 0);
        $tarePrice = (float) ($rawTotal->TotalTarePrice ?? $rawTotal->TarePrice ?? 0);

        if ($totalPrice <= 0 && ! empty($items)) {
            $totalPrice = array_sum(array_column($items, 'total_price'));
        }
        if ($totalPriceWithoutVat <= 0 && ! empty($items)) {
            $totalPriceWithoutVat = array_sum(array_column($items, 'price'));
        }
        if ($totalVat <= 0 && ! empty($items)) {
            $totalVat = array_sum(array_column($items, 'vat_amount'));
        }

        $totals = [
            'price' => $totalPriceWithoutVat,
            'vat' => $totalVat,
            'total_price' => $totalPrice,
            'environmental_fee' => $envFee,
            'tare_price' => $tarePrice,
        ];

        return [
            'type' => 'xml',
            'series' => $series,
            'number' => $num,
            'invoice_number' => $invoiceNumber,
            'supply_date' => $supplyDate,
            'additional_data' => $additionalData,
            'supplier' => [
                'tin' => $supplierTin,
                'name' => $supplierName,
                'vat_number' => $supplierVat,
                'address' => $supplierAddress,
                'bank_name' => $bankName,
                'bank_account' => $bankAccount,
                'supply_location' => $supplyLocation,
            ],
            'buyer' => [
                'tin' => $buyerTin,
                'name' => $buyerName,
                'delivery_location' => $deliveryLocation,
            ],
            'items' => $items,
            'totals' => $totals,
            'raw_xml' => $content,
        ];
    }

    /**
     * Parse Armenian e-Invoicing XLSX register export.
     *
     * @return array<string, mixed>
     */
    public function parseXlsx(string $filePath): array
    {
        $extractor = new IngredientImportService;
        $rows = $extractor->extractRowsFromXlsx($filePath);

        if (empty($rows)) {
            throw new InvalidArgumentException(__('No rows could be read from the Excel file.'));
        }

        // Find header row containing "Սերիա և համար" or "ՀՎՀՀ"
        $headerRowIdx = -1;
        foreach ($rows as $idx => $row) {
            foreach ($row as $cell) {
                if (str_contains((string) $cell, 'Սերիա և համար') || str_contains((string) $cell, 'ՀՎՀՀ')) {
                    $headerRowIdx = $idx;
                    break 2;
                }
            }
        }

        if ($headerRowIdx === -1 || ! isset($rows[$headerRowIdx + 1])) {
            throw new InvalidArgumentException(__('No valid tax invoice records found in Excel file.'));
        }

        $headers = array_map('trim', $rows[$headerRowIdx]);
        $firstData = array_map('trim', $rows[$headerRowIdx + 1]);

        $colSeriesNum = array_search('Սերիա և համար', $headers, true);
        $colTin = array_search('Դուրս գրողի ՀՎՀՀ', $headers, true);
        $colSupplierName = array_search('Դուրս գրողի անվանում', $headers, true);
        $colSupplyDate = array_search('Մատակարարման ա/թ', $headers, true);
        $colTotal = array_search('Ընդհանուր գումար', $headers, true);
        $colSubtotal = array_search('Շրջանառություն առանց ԱԱՀ', $headers, true);
        $colVat = array_search('ԱԱՀ գումար', $headers, true);
        $colSupplyLocation = array_search('Առաքման վայր', $headers, true);
        $colDeliveryLocation = array_search('Նշանակման վայր', $headers, true);
        $colAdditional = array_search('Լրացուցիչ տվյալներ', $headers, true);
        $colBank = array_search('Դուրս գրողի բանկային հաշվեհամար', $headers, true);

        $invoiceNum = (string) ($firstData[$colSeriesNum] ?? 'INV-'.strtoupper(Str::random(6)));
        $supplierTin = (string) ($firstData[$colTin] ?? '');
        $supplierName = (string) ($firstData[$colSupplierName] ?? '');
        $supplyDate = (string) ($firstData[$colSupplyDate] ?? now()->toDateString());
        $totalAmount = (float) ($firstData[$colTotal] ?? 0);
        $subtotal = (float) ($firstData[$colSubtotal] ?? 0);
        $vatAmount = (float) ($firstData[$colVat] ?? 0);

        return [
            'type' => 'xlsx',
            'series' => substr($invoiceNum, 0, 1),
            'number' => substr($invoiceNum, 1),
            'invoice_number' => $invoiceNum,
            'supply_date' => $supplyDate,
            'additional_data' => (string) ($firstData[$colAdditional] ?? ''),
            'supplier' => [
                'tin' => $supplierTin,
                'name' => $supplierName,
                'vat_number' => $supplierTin,
                'address' => '',
                'bank_name' => '',
                'bank_account' => (string) ($firstData[$colBank] ?? ''),
                'supply_location' => (string) ($firstData[$colSupplyLocation] ?? ''),
            ],
            'buyer' => [
                'tin' => '',
                'name' => '',
                'delivery_location' => (string) ($firstData[$colDeliveryLocation] ?? ''),
            ],
            'items' => [],
            'totals' => [
                'price' => $subtotal,
                'vat' => $vatAmount,
                'total_price' => $totalAmount,
                'environmental_fee' => 0,
                'tare_price' => 0,
            ],
        ];
    }

    /**
     * Import parsed invoice into the system, creating suppliers/ingredients and receiving stock.
     */
    public function importInvoice(
        Vendor $vendor,
        array $invoiceData,
        Warehouse $warehouse,
        ?User $user = null
    ): SupplierInvoice {
        return DB::transaction(function () use ($vendor, $invoiceData, $warehouse, $user) {
            $supplierData = $invoiceData['supplier'];

            // 1. Resolve or Create Supplier
            $supplier = null;
            if (! empty($supplierData['tin'])) {
                $supplier = Supplier::where('vendor_id', $vendor->id)
                    ->where('tax_id', $supplierData['tin'])
                    ->first();
            }

            if (! $supplier && ! empty($supplierData['name'])) {
                $supplier = Supplier::where('vendor_id', $vendor->id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($supplierData['name'])])
                    ->first();
            }

            if (! $supplier) {
                $supplier = Supplier::create([
                    'vendor_id' => $vendor->id,
                    'name' => $supplierData['name'] ?: 'Մատակարար '.$supplierData['tin'],
                    'code' => 'SUP-'.strtoupper(Str::random(5)),
                    'tax_id' => $supplierData['tin'] ?: null,
                    'vat_number' => $supplierData['vat_number'] ?: null,
                    'address' => $supplierData['address'] ?: null,
                    'bank_name' => $supplierData['bank_name'] ?: null,
                    'bank_account' => $supplierData['bank_account'] ?: null,
                    'payment_terms' => 30,
                    'is_active' => true,
                ]);
            } else {
                // Update supplier metadata if missing
                if (! $supplier->bank_account && ! empty($supplierData['bank_account'])) {
                    $supplier->bank_account = $supplierData['bank_account'];
                }
                if (! $supplier->vat_number && ! empty($supplierData['vat_number'])) {
                    $supplier->vat_number = $supplierData['vat_number'];
                }
                if (! $supplier->address && ! empty($supplierData['address'])) {
                    $supplier->address = $supplierData['address'];
                }
                $supplier->save();
            }

            // 2. Fetch available vendor units
            $units = Unit::where(fn ($q) => $q->whereNull('vendor_id')->orWhere('vendor_id', $vendor->id))
                ->where('is_active', true)
                ->get();

            $defaultUnit = $units->firstWhere('code', 'piece') ?? $units->firstWhere('code', 'kg') ?? $units->first();

            $ledgerReceiptItems = [];
            $invoiceItemRows = [];

            // 3. Process line items
            foreach ($invoiceData['items'] as $item) {
                $description = $item['description'];
                $classifierCode = $item['classifier_code'] ?: null;
                $qty = (float) $item['quantity'];
                $totalPrice = (float) ($item['total_price'] ?? 0);
                // Unit price including VAT: total price divided by quantity
                $unitPrice = ($qty > 0 && $totalPrice > 0)
                    ? round($totalPrice / $qty, 4)
                    : (float) $item['unit_price'];

                if ($qty <= 0) {
                    continue;
                }

                // Match unit
                $unit = $this->resolveUnit($item['unit_name'], $units, $defaultUnit);

                // Match or create Ingredient
                $ingredient = null;
                if ($classifierCode) {
                    $ingredient = Ingredient::where('vendor_id', $vendor->id)
                        ->where('classifier_code', $classifierCode)
                        ->first();
                }

                if (! $ingredient) {
                    $ingredient = Ingredient::where('vendor_id', $vendor->id)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($description)])
                        ->first();
                }

                if (! $ingredient) {
                    $ingredient = Ingredient::create([
                        'vendor_id' => $vendor->id,
                        'name' => $description,
                        'sku' => 'ING-'.strtoupper(Str::random(6)),
                        'classifier_code' => $classifierCode,
                        'type' => Ingredient::TYPE_RAW_MATERIAL,
                        'unit_id' => $unit->id,
                        'current_cost' => $unitPrice,
                        'last_purchase_price' => $unitPrice,
                        'min_stock' => 0,
                        'is_active' => true,
                    ]);
                } else {
                    $ingredient->last_purchase_price = $unitPrice;
                    $ingredient->current_cost = $unitPrice;
                    if (! $ingredient->classifier_code && $classifierCode) {
                        $ingredient->classifier_code = $classifierCode;
                    }
                    $ingredient->save();
                }

                // Associate ingredient with supplier in supplier_ingredients
                SupplierIngredient::firstOrCreate(
                    [
                        'vendor_id' => $vendor->id,
                        'supplier_id' => $supplier->id,
                        'ingredient_id' => $ingredient->id,
                    ],
                    [
                        'unit_id' => $unit->id,
                        'price' => $unitPrice,
                        'is_primary_supplier' => true,
                    ]
                );

                $ledgerReceiptItems[] = [
                    'ingredient_id' => $ingredient->id,
                    'quantity' => $qty,
                    'unit_id' => $unit->id,
                    'unit_cost' => $unitPrice,
                    'notes' => "Tax Invoice #{$invoiceData['invoice_number']} from {$supplier->name}",
                ];

                $invoiceItemRows[] = [
                    'vendor_id' => $vendor->id,
                    'ingredient_id' => $ingredient->id,
                    'description' => $description,
                    'classifier_code' => $classifierCode,
                    'unit_id' => $unit->id,
                    'unit_name' => $item['unit_name'],
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'price' => (float) $item['price'],
                    'vat_rate' => (float) $item['vat_rate'],
                    'vat_amount' => (float) $item['vat_amount'],
                    'total_price' => (float) $item['total_price'],
                ];
            }

            // 4. Create SupplierInvoice
            $totals = $invoiceData['totals'];
            $supplyDate = $invoiceData['supply_date'] ? Carbon::parse($invoiceData['supply_date'])->toDateString() : now()->toDateString();
            $dueDate = Carbon::parse($supplyDate)->addDays($supplier->payment_terms ?: 30)->toDateString();

            $invoice = SupplierInvoice::create([
                'vendor_id' => $vendor->id,
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'invoice_number' => $invoiceData['invoice_number'],
                'series' => $invoiceData['series'],
                'number' => $invoiceData['number'],
                'invoice_date' => $supplyDate,
                'supply_date' => $supplyDate,
                'due_date' => $dueDate,
                'subtotal' => round($totals['price'], 2),
                'tax_amount' => round($totals['vat'], 2),
                'environmental_fee' => round($totals['environmental_fee'] ?? 0, 2),
                'tare_price' => round($totals['tare_price'] ?? 0, 2),
                'total_amount' => round($totals['total_price'], 2),
                'paid_amount' => 0.0,
                'remaining_balance' => round($totals['total_price'], 2),
                'payment_status' => SupplierInvoice::PAYMENT_UNPAID,
                'supply_location' => $supplierData['supply_location'] ?? null,
                'delivery_location' => $invoiceData['buyer']['delivery_location'] ?? null,
                'additional_data' => $invoiceData['additional_data'] ?? null,
                'notes' => __('Imported from RA e-Invoicing (taxservice.am)'),
                'received_by' => $user?->id,
                'einvoice_xml' => $invoiceData['raw_xml'] ?? null,
            ]);

            // 5. Attach Invoice Items
            foreach ($invoiceItemRows as $itemRow) {
                $itemRow['supplier_invoice_id'] = $invoice->id;
                SupplierInvoiceItem::create($itemRow);
            }

            // 6. Post stock into warehouse ledger if items exist
            if (! empty($ledgerReceiptItems)) {
                $this->ledgerService->recordReceipt(
                    warehouse: $warehouse,
                    items: $ledgerReceiptItems,
                    user: $user,
                    notes: "Tax Invoice #{$invoice->invoice_number} from {$supplier->name}"
                );
            }

            // 7. Update supplier current balance
            $supplier->recalculateBalance();

            return $invoice;
        });
    }

    /**
     * Resolve unit from raw text representation.
     */
    protected function resolveUnit(string $raw, $units, Unit $defaultUnit): Unit
    {
        $clean = mb_strtolower(trim($raw));
        if ($clean === '') {
            return $defaultUnit;
        }

        // Direct code or name match
        $found = $units->first(fn ($u) => mb_strtolower($u->code) === $clean || mb_strtolower($u->name) === $clean);
        if ($found) {
            return $found;
        }

        // Translation map
        if (isset($this->unitMap[$clean])) {
            $code = $this->unitMap[$clean];
            $found = $units->firstWhere('code', $code);
            if ($found) {
                return $found;
            }
        }

        return $defaultUnit;
    }
}
