<?php

namespace App\Services\Fiscal\DTOs;

use Carbon\Carbon;

class FiscalReceiptResult
{
    public function __construct(
        public bool $success,
        public ?string $crn = null,
        public ?string $receiptNumber = null,
        public ?string $fiscalQrData = null,
        public ?Carbon $signedAt = null,
        public float $taxAmount = 0.0,
        public ?string $errorMessage = null,
        public array $rawPayload = []
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'crn' => $this->crn,
            'receipt_number' => $this->receiptNumber,
            'fiscal_qr_data' => $this->fiscalQrData,
            'signed_at' => $this->signedAt?->toISOString(),
            'tax_amount' => $this->taxAmount,
            'error_message' => $this->errorMessage,
            'raw_payload' => $this->rawPayload,
        ];
    }
}
