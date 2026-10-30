<?php

namespace App\Services\Fiscal\Contracts;

use App\Models\Order;
use App\Models\PosPayment;
use App\Models\PosShift;
use App\Services\Fiscal\DTOs\FiscalReceiptResult;
use App\Services\Fiscal\DTOs\FiscalZReportResult;

interface PosFiscalAdapterInterface
{
    /**
     * Submit and fiscalize an order payment receipt with the tax authority / cash register.
     */
    public function printReceipt(Order $order, PosPayment $payment): FiscalReceiptResult;

    /**
     * Print a fiscal refund / return receipt.
     */
    public function printRefundReceipt(Order $order, PosPayment $payment, float $amount): FiscalReceiptResult;

    /**
     * Generate daily fiscal Z-Report for shift closure.
     */
    public function printZReport(PosShift $shift): FiscalZReportResult;

    /**
     * Check communication status of the fiscal machine.
     *
     * @return array{online: bool, crn: ?string, tax_id: ?string, firmware: ?string, error: ?string}
     */
    public function getStatus(): array;
}
