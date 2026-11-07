<?php

declare(strict_types=1);

namespace App\Domains\Wholesale\Services;

use App\Domains\Wholesale\Models\WholesalePekExport;
use App\Domains\Wholesale\Models\WholesaleSale;
use App\Domains\Wholesale\Models\WholesaleTaxDocument;
use App\Domains\Wholesale\Models\WholesaleTaxDocumentItem;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class WholesaleTaxDocumentService
{
    public function __construct(
        protected PekInvoiceXmlGenerator $invoiceGenerator,
        protected PekAccDocXmlGenerator $accDocGenerator,
        protected PekXsdValidationService $xsdValidator
    ) {}

    public function createTaxDocumentFromSale(WholesaleSale $sale, ?User $user = null): WholesaleTaxDocument
    {
        return DB::transaction(function () use ($sale, $user) {
            $sale->loadMissing(['seller.taxProfiles', 'seller.bankAccounts', 'seller.locations', 'customer.addresses', 'items.product']);

            $seller = $sale->seller;
            $customer = $sale->customer;
            $taxProfile = $seller->taxProfile;

            $isVatPayer = $taxProfile ? (bool) $taxProfile->is_vat_payer : ($seller->tax_regime === 'vat_payer');
            $sellerTin = (string) ($seller->tin ?? $seller->tax_number ?? '');
            $sellerAddress = (string) ($seller->address ?? $seller->legal_address ?? '');

            $documentType = $isVatPayer ? 'TAX_INVOICE' : 'ACCOUNTING_DOCUMENT';
            $series = $isVatPayer ? ($taxProfile->document_series ?? 'A') : ($taxProfile->document_series ?? 'B');
            $docNumber = (string) mt_rand(1000000000, 9999999999);

            $primaryAddress = $customer->addresses->firstWhere('is_default', true) ?? $customer->addresses->first();
            $fullAddress = $primaryAddress ? trim(($primaryAddress->address_line1 ?? '').', '.($primaryAddress->city ?? '')) : ($customer->legal_name ?? $customer->name);
            $customerTin = (string) ($customer->tin ?? $customer->tax_number ?? '');

            $customerSnapshot = [
                'tin' => $customerTin,
                'tax_number' => $customerTin,
                'vat_number' => $customer->vat_number ?? (($customer->tax_regime ?? '') === 'vat_payer' && $customerTin ? "{$customerTin}/1" : null),
                'legal_name' => $customer->legal_name ?? $customer->name,
                'legal_address' => $fullAddress,
                'delivery_address' => $fullAddress,
            ];

            $sellerSnapshot = [
                'tin' => $sellerTin,
                'vat_number' => $seller->vat_number ?? ($isVatPayer ? "{$sellerTin}/1" : null),
                'legal_name' => $seller->legal_name ?? $seller->name,
                'trade_name' => $seller->name ?? $seller->legal_name,
                'legal_address' => $sellerAddress,
                'pek_username' => $seller->pek_username ?? $sellerTin,
                'bank_accounts' => $seller->bankAccounts->map(fn ($b) => [
                    'bank_name' => $b->bank_name,
                    'account_number' => $b->account_number,
                ])->toArray(),
                'locations' => $seller->locations->map(fn ($loc) => [
                    'name' => $loc->name,
                    'address' => $loc->address ?? $loc->address_am,
                ])->toArray(),
            ];

            $taxDocument = WholesaleTaxDocument::create([
                'vendor_id' => $sale->vendor_id,
                'seller_id' => $seller->id,
                'customer_id' => $customer->id,
                'sale_id' => $sale->id,
                'order_id' => $sale->order_id,
                'document_type' => $documentType,
                'series' => $series,
                'document_number' => $docNumber,
                'issue_date' => $sale->invoice_date ?? now()->toDateString(),
                'supply_date' => $sale->supply_date ?? $sale->invoice_date ?? now()->toDateString(),
                'status' => 'DRAFT',
                'is_adjustment' => false,
                'currency' => $sale->currency ?? 'AMD',
                'taxable_amount' => $sale->subtotal ?? $sale->subtotal_amount ?? 0.00,
                'vat_amount' => $isVatPayer ? ($sale->tax_total ?? $sale->tax_amount ?? 0.00) : 0.00,
                'total_amount' => $sale->total_amount ?? 0.00,
                'seller_snapshot' => $sellerSnapshot,
                'customer_snapshot' => $customerSnapshot,
                'created_by' => $user?->id,
            ]);

            $lineNumber = 1;
            foreach ($sale->items as $saleItem) {
                $product = $saleItem->product;
                $atgaa = $product?->classifier_code ?? $product?->atgaa_code ?? '1602';
                $unit = $product?->unit?->symbol ?? $product?->unit?->code ?? $product?->unit_of_measure ?? 'հատ';

                if ($isVatPayer) {
                    $itemTaxable = (float) ($saleItem->subtotal ?? $saleItem->line_subtotal ?? 0.00);
                    $itemVat = (float) ($saleItem->vat_amount ?? $saleItem->tax_amount ?? 0.00);
                    $dealType = null;
                    $vatRate = (float) ($saleItem->vat_rate ?? 20.00);
                } else {
                    $itemTaxable = (float) ($saleItem->total_amount ?? $saleItem->total ?? $saleItem->line_total ?? 0.00);
                    $itemVat = null;
                    $dealType = 'SPECIAL_TAX_SYSTEM';
                    $vatRate = null;
                }

                $itemTotal = (float) ($saleItem->total_amount ?? $saleItem->total ?? $saleItem->line_total ?? ($itemTaxable + ($itemVat ?? 0)));

                WholesaleTaxDocumentItem::create([
                    'tax_document_id' => $taxDocument->id,
                    'product_id' => $saleItem->product_id,
                    'line_number' => $lineNumber++,
                    'description' => $saleItem->product_name,
                    'classifier_code' => $atgaa,
                    'unit' => $unit,
                    'quantity' => $saleItem->quantity,
                    'unit_price' => $saleItem->unit_price,
                    'taxable_price' => $itemTaxable,
                    'discount_percent' => $saleItem->discount_percent ?? null,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $itemVat,
                    'deal_type' => $dealType,
                    'total_price' => $itemTotal,
                ]);
            }

            return $this->generateAndValidateXml($taxDocument);
        });
    }

    public function generateAndValidateXml(WholesaleTaxDocument $document): WholesaleTaxDocument
    {
        $document->loadMissing(['items', 'seller', 'customer']);

        $xml = match ($document->document_type) {
            'TAX_INVOICE' => $this->invoiceGenerator->generate($document),
            'ACCOUNTING_DOCUMENT' => $this->accDocGenerator->generate($document),
            default => throw new \InvalidArgumentException("Unsupported document type: {$document->document_type}"),
        };

        $validation = $this->xsdValidator->validateXml($xml, $document->document_type);

        $document->update([
            'xml_content' => $xml,
            'is_xsd_valid' => $validation['isValid'],
            'xsd_validation_errors' => $validation['errors'],
            'status' => 'GENERATED',
        ]);

        return $document;
    }

    public function exportTaxDocumentsAsZip(Vendor $vendor, array $documentIds, ?User $user = null): WholesalePekExport
    {
        $documents = WholesaleTaxDocument::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->whereIn('id', $documentIds)
            ->with(['items'])
            ->get();

        if ($documents->isEmpty()) {
            throw new \InvalidArgumentException('No eligible tax documents found for export.');
        }

        $batchNumber = 'PEK-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2)));
        $storageDir = storage_path('app/wholesale/pek_exports');
        if (! file_exists($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        $zipFileName = "{$batchNumber}.zip";
        $zipFilePath = "{$storageDir}/{$zipFileName}";

        $zip = new ZipArchive;
        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Failed to create ZIP archive at {$zipFilePath}");
        }

        $types = [];

        foreach ($documents as $doc) {
            if (empty($doc->xml_content)) {
                $doc = $this->generateAndValidateXml($doc);
            }

            $fileName = "{$doc->series}{$doc->document_number}.xml";
            $zip->addFromString($fileName, $doc->xml_content);
            $types[$doc->document_type] = true;
        }

        $zip->close();

        $fileSize = (int) filesize($zipFilePath);
        $exportType = count($types) === 1 ? array_key_first($types) : 'MIXED';

        $export = WholesalePekExport::create([
            'vendor_id' => $vendor->id,
            'export_batch_number' => $batchNumber,
            'document_count' => $documents->count(),
            'document_type' => $exportType,
            'storage_path' => "wholesale/pek_exports/{$zipFileName}",
            'file_size_bytes' => $fileSize,
            'status' => 'SUCCESS',
            'document_ids' => $documents->pluck('id')->toArray(),
            'created_by' => $user?->id,
        ]);

        WholesaleTaxDocument::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->whereIn('id', $documents->pluck('id'))
            ->update(['status' => 'EXPORTED']);

        return $export;
    }
}
