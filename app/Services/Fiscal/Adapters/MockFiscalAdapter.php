<?php

namespace App\Services\Fiscal\Adapters;

use App\Models\Order;
use App\Models\PosPayment;
use App\Models\PosShift;
use App\Services\Fiscal\Contracts\PosFiscalAdapterInterface;
use App\Services\Fiscal\DTOs\FiscalReceiptResult;
use App\Services\Fiscal\DTOs\FiscalZReportResult;
use Carbon\Carbon;

class MockFiscalAdapter implements PosFiscalAdapterInterface
{
    public function __construct(
        protected ?string $crn = '00812948',
        protected ?string $taxNumber = '01524311'
    ) {}

    public function printReceipt(Order $order, PosPayment $payment): FiscalReceiptResult
    {
        $signedAt = Carbon::now();
        $crn = $this->crn ?: '00812948';
        $tin = $this->taxNumber ?: '01524311';
        $seq = str_pad((string) ($payment->id % 99999), 5, '0', STR_PAD_LEFT);
        $receiptNumber = "ARM-HDM-{$crn}-{$signedAt->format('Ymd')}-{$seq}";

        // Compute 20% VAT portion if applicable
        $amount = (float) $payment->amount;
        $taxAmount = round($amount * 20 / 120, 2);

        // Armenian SRC / ՊԵԿ standard verification string format
        $fiscalQrData = sprintf(
            'https://tax.src.am/crn/check?crn=%s&seq=%s&tin=%s&sum=%.2f&dt=%s',
            $crn,
            $seq,
            $tin,
            $amount,
            $signedAt->format('YmdHis')
        );

        $payment->update([
            'fiscal_crn' => $crn,
            'fiscal_receipt_number' => $receiptNumber,
            'fiscal_qr_data' => $fiscalQrData,
            'fiscal_signed_at' => $signedAt,
            'fiscal_status' => 'printed',
        ]);

        return new FiscalReceiptResult(
            success: true,
            crn: $crn,
            receiptNumber: $receiptNumber,
            fiscalQrData: $fiscalQrData,
            signedAt: $signedAt,
            taxAmount: $taxAmount,
            errorMessage: null,
            rawPayload: [
                'crn' => $crn,
                'tin' => $tin,
                'seq' => $seq,
                'mode' => 'mock_hdm',
                'payment_method' => $payment->payment_method,
            ]
        );
    }

    public function printRefundReceipt(Order $order, PosPayment $payment, float $amount): FiscalReceiptResult
    {
        $signedAt = Carbon::now();
        $crn = $this->crn ?: '00812948';
        $tin = $this->taxNumber ?: '01524311';
        $seq = str_pad((string) (mt_rand(10000, 99999)), 5, '0', STR_PAD_LEFT);
        $receiptNumber = "ARM-HDM-REFUND-{$crn}-{$signedAt->format('Ymd')}-{$seq}";
        $taxAmount = round($amount * 20 / 120, 2);

        $fiscalQrData = sprintf(
            'https://tax.src.am/crn/check?crn=%s&seq=%s&tin=%s&sum=-%.2f&dt=%s',
            $crn,
            $seq,
            $tin,
            $amount,
            $signedAt->format('YmdHis')
        );

        $payment->update([
            'fiscal_status' => 'refunded',
        ]);

        return new FiscalReceiptResult(
            success: true,
            crn: $crn,
            receiptNumber: $receiptNumber,
            fiscalQrData: $fiscalQrData,
            signedAt: $signedAt,
            taxAmount: -$taxAmount,
            errorMessage: null,
            rawPayload: [
                'crn' => $crn,
                'tin' => $tin,
                'mode' => 'mock_hdm_refund',
                'refund_amount' => $amount,
            ]
        );
    }

    public function printZReport(PosShift $shift): FiscalZReportResult
    {
        $generatedAt = Carbon::now();
        $zNumber = 'Z-'.str_pad((string) $shift->id, 4, '0', STR_PAD_LEFT).'-'.$generatedAt->format('Ymd');
        $totalSales = (float) $shift->total_sales;
        $totalTax = round($totalSales * 20 / 120, 2);

        return new FiscalZReportResult(
            success: true,
            zReportNumber: $zNumber,
            totalSales: $totalSales,
            totalTax: $totalTax,
            generatedAt: $generatedAt,
            errorMessage: null,
            rawPayload: [
                'shift_id' => $shift->id,
                'crn' => $this->crn,
                'cash_sales' => (float) $shift->total_cash_sales,
                'card_sales' => (float) $shift->total_card_sales,
            ]
        );
    }

    public function getStatus(): array
    {
        return [
            'online' => true,
            'crn' => $this->crn ?: '00812948',
            'tax_id' => $this->taxNumber ?: '01524311',
            'firmware' => 'ARM-E-HDM-v2.4',
            'error' => null,
        ];
    }
}
