<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\InventoryBalance;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProcurementService
{
    public function __construct(
        protected InventoryLedgerService $ledgerService,
        protected UnitConversionService $conversionService
    ) {}

    /**
     * Generate replenishment suggestions for low-stock ingredients.
     *
     * @return Collection<int, array{ingredient: Ingredient, current_stock: float, min_stock: float, suggested_qty: float, unit_id: int, supplier: Supplier|null, price: float|null}>
     */
    public function suggestReplenishment(Vendor $vendor, ?Warehouse $warehouse = null): Collection
    {
        $query = Ingredient::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->where('min_stock', '>', 0)
            ->with(['unit', 'supplierIngredients.supplier']);

        $lowStockItems = $query->get()->filter(function (Ingredient $ingredient) use ($warehouse) {
            if ($warehouse) {
                $balance = InventoryBalance::where('warehouse_id', $warehouse->id)
                    ->where('ingredient_id', $ingredient->id)
                    ->first();
                $avail = $balance ? (float) max(0.0, $balance->quantity_on_hand - $balance->quantity_reserved) : 0.0;

                return $avail <= (float) $ingredient->min_stock;
            }

            return $ingredient->isLowStock();
        });

        return $lowStockItems->map(function (Ingredient $ingredient) use ($warehouse) {
            $currentStock = $warehouse
                ? (float) max(0.0, (float) InventoryBalance::where('warehouse_id', $warehouse->id)->where('ingredient_id', $ingredient->id)->value('quantity_on_hand'))
                : (float) $ingredient->total_stock_available;

            $targetStock = (float) ($ingredient->max_stock > 0 ? $ingredient->max_stock : ($ingredient->reorder_point > 0 ? $ingredient->reorder_point : ((float) $ingredient->min_stock * 2.0)));
            $needed = max(1.0, round($targetStock - $currentStock, 4));

            // Find primary or first supplier
            $primaryLink = $ingredient->supplierIngredients->firstWhere('is_primary_supplier', true)
                ?: $ingredient->supplierIngredients->first();

            return [
                'ingredient' => $ingredient,
                'current_stock' => $currentStock,
                'min_stock' => (float) $ingredient->min_stock,
                'suggested_qty' => $needed,
                'unit_id' => $ingredient->unit_id,
                'unit_code' => $ingredient->unit?->code,
                'supplier' => $primaryLink?->supplier,
                'unit_price' => $primaryLink ? (float) $primaryLink->price : (float) $ingredient->last_purchase_price,
            ];
        })->values();
    }

    /**
     * Create a new Purchase Order.
     *
     * @param  array{supplier_id: int, warehouse_id: int, order_date?: string, expected_delivery_date?: string, notes?: string, items: array}  $data
     */
    public function createPurchaseOrder(Vendor $vendor, array $data, ?User $user = null): PurchaseOrder
    {
        return DB::transaction(function () use ($vendor, $data, $user) {
            $poNumber = 'PO-'.date('Ymd').'-'.strtoupper(Str::random(4));

            $po = PurchaseOrder::create([
                'vendor_id' => $vendor->id,
                'po_number' => $poNumber,
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'payment_status' => PurchaseOrder::PAYMENT_UNPAID,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            $subtotal = 0.0;
            $lineDiscountTotal = 0.0;
            $totalTax = 0.0;

            foreach ($data['items'] as $itemData) {
                if (empty($itemData['ingredient_id']) || empty($itemData['quantity'])) {
                    continue;
                }

                $qty = (float) $itemData['quantity'];
                $price = (float) $itemData['unit_price'];
                $lineBase = $qty * $price;
                $discPercent = (float) ($itemData['discount_percent'] ?? 0.0);
                $discAmount = round($lineBase * ($discPercent / 100.0), 2);
                $netBase = max(0.0, $lineBase - $discAmount);

                $taxPercent = (float) ($itemData['tax_percent'] ?? 0.0);
                $taxAmount = round($netBase * ($taxPercent / 100.0), 2);
                $total = round($netBase + $taxAmount, 2);

                PurchaseOrderItem::create([
                    'vendor_id' => $vendor->id,
                    'purchase_order_id' => $po->id,
                    'ingredient_id' => $itemData['ingredient_id'],
                    'classifier_code' => $itemData['classifier_code'] ?? null,
                    'quantity_ordered' => $qty,
                    'quantity_received' => 0,
                    'unit_id' => $itemData['unit_id'],
                    'unit_price' => $price,
                    'discount_percent' => $discPercent,
                    'discount_amount' => $discAmount,
                    'tax_percent' => $taxPercent,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $total,
                    'notes' => $itemData['notes'] ?? null,
                ]);

                $subtotal += $lineBase;
                $lineDiscountTotal += $discAmount;
                $totalTax += $taxAmount;
            }

            $orderDiscPercent = (float) ($data['discount_percent'] ?? 0.0);
            $orderDiscAmount = $orderDiscPercent > 0 ? round(($subtotal - $lineDiscountTotal) * ($orderDiscPercent / 100.0), 2) : 0.0;
            $finalDiscount = round($lineDiscountTotal + $orderDiscAmount, 2);

            $po->subtotal = round($subtotal, 2);
            $po->discount_percent = $orderDiscPercent;
            $po->discount_amount = $finalDiscount;
            $po->tax_amount = round($totalTax, 2);
            $po->total_amount = max(0.0, round($subtotal - $finalDiscount + $totalTax, 2));
            $po->save();

            return $po;
        });
    }

    /**
     * Update an existing purchase order and its line items.
     *
     * @param  array<string, mixed>  $data
     */
    public function updatePurchaseOrder(PurchaseOrder $po, array $data, ?User $user = null): PurchaseOrder
    {
        if (
            $po->status === PurchaseOrder::STATUS_RECEIVED ||
            $po->status === PurchaseOrder::STATUS_PARTIALLY_RECEIVED ||
            $po->items()->where('quantity_received', '>', 0)->exists()
        ) {
            throw new InvalidArgumentException(__('Cannot edit a purchase order that has already received stock.'));
        }

        return DB::transaction(function () use ($po, $data) {
            $po->supplier_id = $data['supplier_id'];
            $po->warehouse_id = $data['warehouse_id'];
            $po->order_date = $data['order_date'] ?? $po->order_date;
            $po->expected_delivery_date = $data['expected_delivery_date'] ?? null;
            $po->notes = $data['notes'] ?? null;

            // Delete old line items
            $po->items()->delete();

            $subtotal = 0.0;
            $lineDiscountTotal = 0.0;
            $totalTax = 0.0;

            foreach ($data['items'] as $itemData) {
                if (empty($itemData['ingredient_id']) || empty($itemData['quantity'])) {
                    continue;
                }

                $qty = (float) $itemData['quantity'];
                $price = (float) $itemData['unit_price'];
                $lineBase = $qty * $price;
                $discPercent = (float) ($itemData['discount_percent'] ?? 0.0);
                $discAmount = round($lineBase * ($discPercent / 100.0), 2);
                $netBase = max(0.0, $lineBase - $discAmount);

                $taxPercent = (float) ($itemData['tax_percent'] ?? 0.0);
                $taxAmount = round($netBase * ($taxPercent / 100.0), 2);
                $total = round($netBase + $taxAmount, 2);

                PurchaseOrderItem::create([
                    'vendor_id' => $po->vendor_id,
                    'purchase_order_id' => $po->id,
                    'ingredient_id' => $itemData['ingredient_id'],
                    'classifier_code' => $itemData['classifier_code'] ?? null,
                    'quantity_ordered' => $qty,
                    'quantity_received' => 0,
                    'unit_id' => $itemData['unit_id'],
                    'unit_price' => $price,
                    'discount_percent' => $discPercent,
                    'discount_amount' => $discAmount,
                    'tax_percent' => $taxPercent,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $total,
                    'notes' => $itemData['notes'] ?? null,
                ]);

                $subtotal += $lineBase;
                $lineDiscountTotal += $discAmount;
                $totalTax += $taxAmount;
            }

            $orderDiscPercent = (float) ($data['discount_percent'] ?? 0.0);
            $orderDiscAmount = $orderDiscPercent > 0 ? round(($subtotal - $lineDiscountTotal) * ($orderDiscPercent / 100.0), 2) : 0.0;
            $finalDiscount = round($lineDiscountTotal + $orderDiscAmount, 2);

            $po->subtotal = round($subtotal, 2);
            $po->discount_percent = $orderDiscPercent;
            $po->discount_amount = $finalDiscount;
            $po->tax_amount = round($totalTax, 2);
            $po->total_amount = max(0.0, round($subtotal - $finalDiscount + $totalTax, 2));
            $po->save();

            return $po;
        });
    }

    /**
     * Delete an unreceived purchase order.
     */
    public function deletePurchaseOrder(PurchaseOrder $po, ?User $user = null): bool
    {
        if (
            $po->status === PurchaseOrder::STATUS_RECEIVED ||
            $po->status === PurchaseOrder::STATUS_PARTIALLY_RECEIVED ||
            $po->items()->where('quantity_received', '>', 0)->exists() ||
            $po->invoices()->exists()
        ) {
            throw new InvalidArgumentException(__('Cannot delete a purchase order that has already received goods or linked invoices.'));
        }

        return DB::transaction(function () use ($po) {
            $po->items()->delete();

            return (bool) $po->delete();
        });
    }

    /**
     * Approve a Purchase Order.
     */
    public function approvePurchaseOrder(PurchaseOrder $po, User $user): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_DRAFT && $po->status !== PurchaseOrder::STATUS_PENDING_APPROVAL) {
            throw new InvalidArgumentException("Purchase Order #{$po->po_number} is already {$po->status}.");
        }

        $po->status = PurchaseOrder::STATUS_APPROVED;
        $po->approved_by = $user->id;
        $po->approved_at = now();
        $po->save();

        return $po;
    }

    /**
     * Mark Purchase Order as sent to supplier.
     */
    public function markAsSent(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_APPROVED) {
            throw new InvalidArgumentException('Only approved purchase orders can be marked as sent.');
        }

        $po->status = PurchaseOrder::STATUS_SENT;
        $po->save();

        return $po;
    }

    /**
     * Receive goods from a Purchase Order into warehouse stock.
     * Creates stock receipt transaction, updates WAC & balances, and records SupplierInvoice.
     *
     * @param  array<int, array{item_id: int, quantity_received: float, unit_price?: float, batch_number?: string, expiry_date?: string}>  $receivedItems
     */
    public function receiveGoodsFromPo(
        PurchaseOrder $po,
        array $receivedItems,
        string $invoiceNumber,
        ?string $dueDate = null,
        ?User $user = null,
        ?string $notes = null
    ): SupplierInvoice {
        return DB::transaction(function () use ($po, $receivedItems, $invoiceNumber, $dueDate, $user, $notes) {
            $warehouse = $po->warehouse;
            $vendorId = $po->vendor_id;
            $po->load('items.ingredient');

            $receiptItemsForLedger = [];
            $invoiceSubtotal = 0.0;
            $invoiceTax = 0.0;

            foreach ($receivedItems as $rec) {
                $poItem = $po->items->firstWhere('id', $rec['item_id']);
                if (! $poItem) {
                    continue;
                }

                $qtyReceived = (float) ($rec['quantity_received'] ?? 0);
                if ($qtyReceived <= 0) {
                    continue;
                }

                $unitPrice = isset($rec['unit_price']) ? (float) $rec['unit_price'] : (float) $poItem->unit_price;
                $taxPercent = (float) $poItem->tax_percent;
                $lineSubtotal = $qtyReceived * $unitPrice;
                $lineTax = round($lineSubtotal * ($taxPercent / 100.0), 2);

                $invoiceSubtotal += $lineSubtotal;
                $invoiceTax += $lineTax;

                // Update PO item received quantity
                $poItem->quantity_received = (float) $poItem->quantity_received + $qtyReceived;
                $poItem->save();

                // Prepare item for Inventory Ledger
                $receiptItemsForLedger[] = [
                    'ingredient_id' => $poItem->ingredient_id,
                    'quantity' => $qtyReceived,
                    'unit_id' => $poItem->unit_id,
                    'unit_cost' => $unitPrice,
                    'notes' => "Received from PO #{$po->po_number}. Invoice #{$invoiceNumber}",
                ];
            }

            if (empty($receiptItemsForLedger)) {
                throw new InvalidArgumentException('No valid received quantities provided.');
            }

            // 1. Record stock receipt via InventoryLedgerService
            $this->ledgerService->recordReceipt(
                warehouse: $warehouse,
                items: $receiptItemsForLedger,
                user: $user,
                notes: "PO Receipt #{$po->po_number} - Inv #{$invoiceNumber}"
            );

            // 2. Check if PO is completely or partially received
            $allReceived = true;
            $po->refresh();
            foreach ($po->items as $item) {
                if ((float) $item->quantity_received < (float) $item->quantity_ordered) {
                    $allReceived = false;
                    break;
                }
            }

            $po->status = $allReceived ? PurchaseOrder::STATUS_RECEIVED : PurchaseOrder::STATUS_PARTIALLY_RECEIVED;
            $po->save();

            // 3. Create Supplier Invoice (Accounts Payable)
            $totalAmount = round($invoiceSubtotal + $invoiceTax, 2);
            $invoiceDueDate = $dueDate ? Carbon::parse($dueDate)->toDateString() : now()->addDays(30)->toDateString();

            $invoice = SupplierInvoice::create([
                'vendor_id' => $vendorId,
                'supplier_id' => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'warehouse_id' => $warehouse->id,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => now()->toDateString(),
                'due_date' => $invoiceDueDate,
                'subtotal' => round($invoiceSubtotal, 2),
                'tax_amount' => round($invoiceTax, 2),
                'total_amount' => $totalAmount,
                'paid_amount' => 0.0,
                'remaining_balance' => $totalAmount,
                'payment_status' => SupplierInvoice::PAYMENT_UNPAID,
                'received_by' => $user?->id,
                'notes' => $notes,
            ]);

            // Update supplier balance
            $po->supplier->recalculateBalance();

            return $invoice;
        });
    }

    /**
     * Record a direct stock receipt without prior Purchase Order.
     *
     * @param  array<int, array{ingredient_id: int, quantity: float, unit_id: int, unit_cost: float, tax_percent?: float}>  $items
     */
    public function receiveDirect(
        Supplier $supplier,
        Warehouse $warehouse,
        array $items,
        string $invoiceNumber,
        ?string $invoiceDate = null,
        ?string $dueDate = null,
        ?User $user = null,
        ?string $notes = null
    ): SupplierInvoice {
        return DB::transaction(function () use ($supplier, $warehouse, $items, $invoiceNumber, $invoiceDate, $dueDate, $user, $notes) {
            $ledgerItems = [];
            $subtotal = 0.0;
            $taxTotal = 0.0;

            foreach ($items as $item) {
                $qty = (float) $item['quantity'];
                $price = (float) $item['unit_cost'];
                $taxPercent = (float) ($item['tax_percent'] ?? 0);
                $lineSubtotal = $qty * $price;
                $lineTax = round($lineSubtotal * ($taxPercent / 100.0), 2);

                $subtotal += $lineSubtotal;
                $taxTotal += $lineTax;

                $ledgerItems[] = [
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity' => $qty,
                    'unit_id' => $item['unit_id'],
                    'unit_cost' => $price,
                    'notes' => "Direct receipt from {$supplier->name}. Inv #{$invoiceNumber}",
                ];
            }

            // Post to inventory ledger
            $this->ledgerService->recordReceipt(
                warehouse: $warehouse,
                items: $ledgerItems,
                user: $user,
                notes: "Direct Receipt - Supplier: {$supplier->name} - Inv #{$invoiceNumber}"
            );

            $totalAmount = round($subtotal + $taxTotal, 2);

            $invoice = SupplierInvoice::create([
                'vendor_id' => $warehouse->vendor_id,
                'supplier_id' => $supplier->id,
                'purchase_order_id' => null,
                'warehouse_id' => $warehouse->id,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invoiceDate ?: now()->toDateString(),
                'due_date' => $dueDate ?: now()->addDays(30)->toDateString(),
                'subtotal' => round($subtotal, 2),
                'tax_amount' => round($taxTotal, 2),
                'total_amount' => $totalAmount,
                'paid_amount' => 0.0,
                'remaining_balance' => $totalAmount,
                'payment_status' => SupplierInvoice::PAYMENT_UNPAID,
                'received_by' => $user?->id,
                'notes' => $notes,
            ]);

            $supplier->recalculateBalance();

            return $invoice;
        });
    }

    /**
     * Record a disbursement payment to a supplier and apply to invoices.
     */
    public function recordPayment(
        Supplier $supplier,
        float $amount,
        string $paymentMethod = SupplierPayment::METHOD_BANK_TRANSFER,
        ?SupplierInvoice $invoice = null,
        ?string $paymentDate = null,
        ?string $referenceNumber = null,
        ?string $notes = null,
        ?User $user = null
    ): SupplierPayment {
        return DB::transaction(function () use ($supplier, $amount, $paymentMethod, $invoice, $paymentDate, $referenceNumber, $notes, $user) {
            if ($amount <= 0) {
                throw new InvalidArgumentException('Payment amount must be greater than zero.');
            }

            $paymentNumber = 'PAY-'.date('Ymd').'-'.strtoupper(Str::random(4));

            $payment = SupplierPayment::create([
                'vendor_id' => $supplier->vendor_id,
                'supplier_id' => $supplier->id,
                'supplier_invoice_id' => $invoice?->id,
                'payment_number' => $paymentNumber,
                'payment_date' => $paymentDate ?: now()->toDateString(),
                'amount' => round($amount, 2),
                'payment_method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'created_by' => $user?->id,
            ]);

            if ($invoice) {
                $invoice->recalculatePaymentState();
            } else {
                // Apply payment to oldest unpaid invoices (FIFO)
                $remainingToApply = $amount;
                $unpaidInvoices = SupplierInvoice::where('supplier_id', $supplier->id)
                    ->whereIn('payment_status', [SupplierInvoice::PAYMENT_UNPAID, SupplierInvoice::PAYMENT_PARTIALLY_PAID, SupplierInvoice::PAYMENT_OVERDUE])
                    ->orderBy('invoice_date')
                    ->get();

                foreach ($unpaidInvoices as $inv) {
                    if ($remainingToApply <= 0) {
                        break;
                    }

                    $due = (float) $inv->remaining_balance;
                    $allocate = min($due, $remainingToApply);

                    $inv->paid_amount = (float) $inv->paid_amount + $allocate;
                    $inv->remaining_balance = max(0.0, round($due - $allocate, 2));
                    $inv->payment_status = $inv->remaining_balance <= 0
                        ? SupplierInvoice::PAYMENT_PAID
                        : SupplierInvoice::PAYMENT_PARTIALLY_PAID;
                    $inv->save();

                    $remainingToApply -= $allocate;
                }
            }

            $supplier->recalculateBalance();

            return $payment;
        });
    }
}
