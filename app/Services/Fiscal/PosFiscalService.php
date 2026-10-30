<?php

namespace App\Services\Fiscal;

use App\Models\Order;
use App\Models\PosPayment;
use App\Models\PosSetting;
use App\Models\PosShift;
use App\Models\Vendor;
use App\Services\Fiscal\Adapters\ArmenianHdmFiscalAdapter;
use App\Services\Fiscal\Adapters\MockFiscalAdapter;
use App\Services\Fiscal\Contracts\PosFiscalAdapterInterface;
use App\Services\Fiscal\DTOs\FiscalReceiptResult;
use App\Services\Fiscal\DTOs\FiscalZReportResult;

class PosFiscalService
{
    /**
     * Resolve the configured fiscal adapter for a vendor/location.
     */
    public function getAdapter(Vendor $vendor, ?int $locationId = null): PosFiscalAdapterInterface
    {
        $setting = PosSetting::where('vendor_id', $vendor->id)
            ->where(function ($q) use ($locationId) {
                if ($locationId) {
                    $q->where('location_id', $locationId)->orWhereNull('location_id');
                } else {
                    $q->whereNull('location_id');
                }
            })
            ->first();

        $adapterType = $setting?->fiscal_adapter ?: 'mock';
        $crn = $setting?->fiscal_crn ?: '00812948';
        $taxNumber = $setting?->fiscal_tax_number ?: '01524311';

        if ($adapterType === 'armenian_hdm') {
            return new ArmenianHdmFiscalAdapter(
                ipAddress: $setting?->fiscal_ip_address ?: '127.0.0.1',
                port: $setting?->fiscal_port ?: 8585,
                crn: $crn,
                taxNumber: $taxNumber
            );
        }

        return new MockFiscalAdapter(
            crn: $crn,
            taxNumber: $taxNumber
        );
    }

    /**
     * Fiscalize an order payment receipt.
     */
    public function fiscalizePayment(Order $order, PosPayment $payment): FiscalReceiptResult
    {
        $vendor = $order->vendor ?: Vendor::find($order->vendor_id);
        $adapter = $this->getAdapter($vendor, $order->location_id);

        return $adapter->printReceipt($order, $payment);
    }

    /**
     * Fiscalize a payment refund.
     */
    public function fiscalizeRefund(Order $order, PosPayment $payment, float $amount): FiscalReceiptResult
    {
        $vendor = $order->vendor ?: Vendor::find($order->vendor_id);
        $adapter = $this->getAdapter($vendor, $order->location_id);

        return $adapter->printRefundReceipt($order, $payment, $amount);
    }

    /**
     * Generate daily fiscal Z-Report for a POS shift.
     */
    public function generateZReport(PosShift $shift): FiscalZReportResult
    {
        $vendor = $shift->vendor ?: Vendor::find($shift->vendor_id);
        $adapter = $this->getAdapter($vendor, $shift->location_id);

        return $adapter->printZReport($shift);
    }
}
