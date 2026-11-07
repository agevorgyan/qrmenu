<?php

namespace App\Domains\Wholesale\Enums;

enum SellerTaxRegime: string
{
    case VAT_PAYER = 'vat_payer';
    case TURNOVER_TAX = 'turnover_tax';
    case MICRO_BUSINESS = 'micro_business';

    public function isVatPayer(): bool
    {
        return $this === self::VAT_PAYER;
    }

    public function defaultDocumentSeries(): string
    {
        return $this === self::VAT_PAYER ? 'A' : 'B';
    }

    public function label(): string
    {
        return match ($this) {
            self::VAT_PAYER => __('ԱԱՀ վճարող (20% VAT Payer)'),
            self::TURNOVER_TAX => __('Շրջանառության հարկ (Turnover Tax)'),
            self::MICRO_BUSINESS => __('Միկրոձեռնարկատիրություն (Micro Business)'),
        };
    }

    public function documentTypeName(): string
    {
        return match ($this) {
            self::VAT_PAYER => __('Հարկային հաշիվ (Tax Invoice)'),
            self::TURNOVER_TAX, self::MICRO_BUSINESS => __('Հաշիվ վավերագիր (Accounting Document)'),
        };
    }
}
