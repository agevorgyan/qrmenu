<?php

namespace App\Services\Fiscal\Adapters;

use App\Models\Order;
use App\Models\PosPayment;
use App\Models\PosShift;
use App\Services\Fiscal\Contracts\PosFiscalAdapterInterface;
use App\Services\Fiscal\DTOs\FiscalReceiptResult;
use App\Services\Fiscal\DTOs\FiscalZReportResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArmenianHdmFiscalAdapter implements PosFiscalAdapterInterface
{
    public function __construct(
        protected ?string $ipAddress = '127.0.0.1',
        protected ?int $port = 8585,
        protected ?string $crn = '00812948',
        protected ?string $taxNumber = '01524311'
    ) {}

    public function printReceipt(Order $order, PosPayment $payment): FiscalReceiptResult
    {
        $signedAt = Carbon::now();
        $amount = (float) $payment->amount;
        $taxAmount = round($amount * 20 / 120, 2);

        $payload = [
            'command' => 'printReceipt',
            'crn' => $this->crn,
            'tin' => $this->taxNumber,
            'order_number' => $order->order_number,
            'total_amount' => $amount,
            'tax_amount' => $taxAmount,
            'payment_type' => $payment->payment_method === 'cash' ? 1 : 2, // 1=Cash, 2=Card
            'cash_tendered' => (float) $payment->cash_tendered,
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name ?: 'Dish',
                'price' => (float) $item->unit_price,
                'quantity' => (float) $item->quantity,
                'total' => (float) $item->total_price,
                'vat_rate' => 20,
            ])->toArray(),
        ];

        try {
            $url = "http://{$this->ipAddress}:{$this->port}/api/fiscal/receipt";
            $response = Http::timeout(4)->asJson()->post($url, $payload);

            if ($response->successful() && ! empty($response->json('receipt_number'))) {
                $receiptNumber = $response->json('receipt_number');
                $qrData = $response->json('qr_data') ?: "https://tax.src.am/crn/check?crn={$this->crn}&sum={$amount}";

                $payment->update([
                    'fiscal_crn' => $this->crn,
                    'fiscal_receipt_number' => $receiptNumber,
                    'fiscal_qr_data' => $qrData,
                    'fiscal_signed_at' => $signedAt,
                    'fiscal_status' => 'printed',
                ]);

                return new FiscalReceiptResult(
                    success: true,
                    crn: $this->crn,
                    receiptNumber: $receiptNumber,
                    fiscalQrData: $qrData,
                    signedAt: $signedAt,
                    taxAmount: $taxAmount,
                    rawPayload: $response->json()
                );
            }

            Log::warning('Armenian HDM fiscal print failed', ['response' => $response->body()]);
        } catch (\Throwable $e) {
            Log::error('Armenian HDM connection exception: '.$e->getMessage());
        }

        // Return failed result
        $payment->update(['fiscal_status' => 'failed']);

        return new FiscalReceiptResult(
            success: false,
            crn: $this->crn,
            receiptNumber: null,
            fiscalQrData: null,
            signedAt: null,
            taxAmount: 0.0,
            errorMessage: 'Fiscal cash register offline or unreachable.'
        );
    }

    public function printRefundReceipt(Order $order, PosPayment $payment, float $amount): FiscalReceiptResult
    {
        $signedAt = Carbon::now();

        try {
            $url = "http://{$this->ipAddress}:{$this->port}/api/fiscal/refund";
            $response = Http::timeout(4)->asJson()->post($url, [
                'crn' => $this->crn,
                'fiscal_receipt_number' => $payment->fiscal_receipt_number,
                'refund_amount' => $amount,
            ]);

            if ($response->successful()) {
                $refundReceipt = $response->json('refund_receipt_number') ?: "ARM-HDM-REF-{$this->crn}";
                $payment->update(['fiscal_status' => 'refunded']);

                return new FiscalReceiptResult(
                    success: true,
                    crn: $this->crn,
                    receiptNumber: $refundReceipt,
                    fiscalQrData: $response->json('qr_data'),
                    signedAt: $signedAt,
                    taxAmount: -round($amount * 20 / 120, 2),
                    rawPayload: $response->json()
                );
            }
        } catch (\Throwable $e) {
            Log::error('Armenian HDM refund exception: '.$e->getMessage());
        }

        return new FiscalReceiptResult(
            success: false,
            errorMessage: 'Fiscal machine unreachable for refund.'
        );
    }

    public function printZReport(PosShift $shift): FiscalZReportResult
    {
        try {
            $url = "http://{$this->ipAddress}:{$this->port}/api/fiscal/z-report";
            $response = Http::timeout(5)->asJson()->post($url, ['shift_id' => $shift->id]);

            if ($response->successful()) {
                return new FiscalZReportResult(
                    success: true,
                    zReportNumber: $response->json('z_number') ?: 'Z-'.now()->format('YmdHis'),
                    totalSales: (float) ($response->json('total_sales') ?? $shift->total_sales),
                    totalTax: (float) ($response->json('total_tax') ?? ($shift->total_sales * 20 / 120)),
                    generatedAt: now(),
                    rawPayload: $response->json()
                );
            }
        } catch (\Throwable $e) {
            Log::error('Armenian HDM Z-Report exception: '.$e->getMessage());
        }

        return new FiscalZReportResult(
            success: false,
            errorMessage: 'Could not generate HDM Z-Report: fiscal printer disconnected.'
        );
    }

    public function getStatus(): array
    {
        try {
            $url = "http://{$this->ipAddress}:{$this->port}/api/fiscal/status";
            $response = Http::timeout(2)->get($url);

            if ($response->successful()) {
                return [
                    'online' => true,
                    'crn' => $response->json('crn', $this->crn),
                    'tax_id' => $response->json('tin', $this->taxNumber),
                    'firmware' => $response->json('firmware', 'ARM-HDM-v1.0'),
                    'error' => null,
                ];
            }
        } catch (\Throwable) {
            // Offline
        }

        return [
            'online' => false,
            'crn' => $this->crn,
            'tax_id' => $this->taxNumber,
            'firmware' => null,
            'error' => 'Device unreachable on local IP/Port.',
        ];
    }
}
