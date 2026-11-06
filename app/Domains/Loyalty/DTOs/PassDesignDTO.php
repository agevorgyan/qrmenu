<?php

namespace App\Domains\Loyalty\DTOs;

readonly class PassDesignDTO
{
    /**
     * @param  array<int, string>|null  $secondaryFields
     * @param  array<int, string>|null  $auxiliaryFields
     * @param  array<string, string>|null  $backFields
     */
    public function __construct(
        public string $backgroundColor = '#1e293b',
        public string $foregroundColor = '#ffffff',
        public string $labelColor = '#94a3b8',
        public ?string $logoUrl = null,
        public ?string $stripImageUrl = null,
        public ?string $iconUrl = null,
        public string $cardTitle = 'Member Pass',
        public string $headerField = 'points',
        public string $primaryField = 'customer_name',
        public ?array $secondaryFields = null,
        public ?array $auxiliaryFields = null,
        public ?array $backFields = null,
        public string $barcodeType = 'PKBarcodeFormatQR',
    ) {}
}
