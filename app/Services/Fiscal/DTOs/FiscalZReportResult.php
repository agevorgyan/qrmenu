<?php

namespace App\Services\Fiscal\DTOs;

use Carbon\Carbon;

class FiscalZReportResult
{
    public function __construct(
        public bool $success,
        public ?string $zReportNumber = null,
        public float $totalSales = 0.0,
        public float $totalTax = 0.0,
        public ?Carbon $generatedAt = null,
        public ?string $errorMessage = null,
        public array $rawPayload = []
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'z_report_number' => $this->zReportNumber,
            'total_sales' => $this->totalSales,
            'total_tax' => $this->totalTax,
            'generated_at' => $this->generatedAt?->toISOString(),
            'error_message' => $this->errorMessage,
            'raw_payload' => $this->rawPayload,
        ];
    }
}
