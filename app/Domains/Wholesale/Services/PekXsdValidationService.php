<?php

declare(strict_types=1);

namespace App\Domains\Wholesale\Services;

use DOMDocument;
use LibXMLError;

class PekXsdValidationService
{
    protected string $schemaBasePath;

    public function __construct(?string $schemaBasePath = null)
    {
        $this->schemaBasePath = $schemaBasePath ?? app_path('Domains/Wholesale/Resources/schemas');
    }

    /**
     * @return array{isValid: bool, errors: array<string>}
     */
    public function validateXml(string $xmlContent, string $documentType): array
    {
        $schemaFile = match ($documentType) {
            'TAX_INVOICE' => $this->schemaBasePath.'/Invoice-1.0.xsd',
            'ACCOUNTING_DOCUMENT' => $this->schemaBasePath.'/AccountingDocument-1.0.xsd',
            default => throw new \InvalidArgumentException("Unsupported document type: {$documentType}"),
        };

        if (! file_exists($schemaFile)) {
            return [
                'isValid' => false,
                'errors' => ["XSD schema file not found at: {$schemaFile}"],
            ];
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        libxml_clear_errors();

        $loaded = $dom->loadXML($xmlContent);
        if (! $loaded) {
            $errors = array_map(
                static fn (LibXMLError $err): string => trim($err->message),
                libxml_get_errors()
            );
            libxml_clear_errors();

            return [
                'isValid' => false,
                'errors' => $errors ?: ['Malformed XML document.'],
            ];
        }

        $isValid = $dom->schemaValidate($schemaFile);
        $errors = [];

        if (! $isValid) {
            $errors = array_map(
                static fn (LibXMLError $err): string => trim($err->message),
                libxml_get_errors()
            );
        }

        libxml_clear_errors();

        return [
            'isValid' => $isValid,
            'errors' => $errors,
        ];
    }
}
