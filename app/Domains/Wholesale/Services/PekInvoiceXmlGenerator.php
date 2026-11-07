<?php

declare(strict_types=1);

namespace App\Domains\Wholesale\Services;

use App\Domains\Wholesale\Models\WholesaleTaxDocument;
use DOMDocument;

class PekInvoiceXmlGenerator
{
    public const XMLNS = 'http://www.taxservice.am/tp3/invoice/definitions';

    public const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    public function generate(WholesaleTaxDocument $document): string
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::XMLNS, 'ExportedData');
        $dom->appendChild($root);

        $signedData = $dom->createElement('SignedData');
        $root->appendChild($signedData);

        $data = $dom->createElement('Data');
        $signedData->appendChild($data);

        // SignableData
        $signableData = $dom->createElement('SignableData');
        $signableData->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::XSI);
        $signableData->setAttribute('Version', '1.0');
        $signableData->setAttributeNS(self::XSI, 'xsi:type', 'Invoice');
        $data->appendChild($signableData);

        $signableData->appendChild($dom->createElement('Type', '1'));
        $signableData->appendChild($dom->createElement('Traceable', 'false'));

        $user = $document->seller_snapshot['tin'] ?? 'wholesale';
        $signableData->appendChild($dom->createElement('User', (string) $user));

        // GeneralInfo
        $generalInfo = $dom->createElement('GeneralInfo');
        $signableData->appendChild($generalInfo);

        if ($document->is_adjustment && $document->parent_document_number) {
            $generalInfo->appendChild($dom->createElement('ParentInvoiceNumber', $document->parent_document_number));
            if ($document->parent_document_date) {
                $generalInfo->appendChild($dom->createElement('ParentInvoiceSubmitDate', $document->parent_document_date->format('Y-m-d').'+04:00'));
            }
        }

        $invNumber = $dom->createElement('InvoiceNumber');
        $invNumber->appendChild($dom->createElement('Number', $document->document_number));
        $invNumber->appendChild($dom->createElement('Series', $document->series));
        $generalInfo->appendChild($invNumber);

        if ($document->is_adjustment) {
            $generalInfo->appendChild($dom->createElement('AdjustmentDiffFlag', '-1'));
        }

        $supplyDate = $document->supply_date ? $document->supply_date->format('Y-m-d').'+04:00' : now()->format('Y-m-d').'+04:00';
        $generalInfo->appendChild($dom->createElement('SupplyDate', $supplyDate));
        $generalInfo->appendChild($dom->createElement('Procedure', $document->is_adjustment ? '2' : '1'));
        $generalInfo->appendChild($dom->createElement('AdjustmentAccount', $document->is_adjustment ? 'true' : 'false'));

        // SupplierInfo
        $supplierInfo = $dom->createElement('SupplierInfo');
        $signableData->appendChild($supplierInfo);

        $sellerSnapshot = $document->seller_snapshot ?? [];
        $sellerTin = (string) ($sellerSnapshot['tin'] ?? '');
        $sellerVat = ! empty($sellerSnapshot['vat_number']) ? (string) $sellerSnapshot['vat_number'] : "{$sellerTin}/1";
        $supplierInfo->appendChild($dom->createElement('VATNumber', $sellerVat));

        $taxpayer = $dom->createElement('Taxpayer');
        $taxpayer->appendChild($dom->createElement('TIN', $sellerTin));
        $taxpayer->appendChild($dom->createElement('Name', (string) ($sellerSnapshot['legal_name'] ?? '')));
        $taxpayer->appendChild($dom->createElement('Address', (string) ($sellerSnapshot['legal_address'] ?? '')));

        if (! empty($sellerSnapshot['bank_accounts'][0])) {
            $bankAcc = $dom->createElement('BankAccount');
            $bankAcc->appendChild($dom->createElement('BankName', (string) ($sellerSnapshot['bank_accounts'][0]['bank_name'] ?? '')));
            $bankAcc->appendChild($dom->createElement('BankAccountNumber', (string) ($sellerSnapshot['bank_accounts'][0]['account_number'] ?? '')));
            $taxpayer->appendChild($bankAcc);
        }
        $supplierInfo->appendChild($taxpayer);

        $supplyLocation = ! empty($sellerSnapshot['locations'][0]['address'])
            ? $sellerSnapshot['locations'][0]['address']
            : (! empty($sellerSnapshot['locations'][0]['address_am'])
                ? $sellerSnapshot['locations'][0]['address_am']
                : ($sellerSnapshot['legal_address'] ?? 'Հայաստան'));
        $supplierInfo->appendChild($dom->createElement('SupplyLocation', (string) $supplyLocation));
        $supplierInfo->appendChild($dom->createElement('SourceIsCar', 'false'));

        // BuyerInfo
        $buyerInfo = $dom->createElement('BuyerInfo');
        $signableData->appendChild($buyerInfo);

        $custSnapshot = $document->customer_snapshot ?? [];
        $custTin = (string) ($custSnapshot['tax_number'] ?? '');
        if (! empty($custSnapshot['vat_number'])) {
            $buyerInfo->appendChild($dom->createElement('VATNumber', (string) $custSnapshot['vat_number']));
        }

        $buyerTaxpayer = $dom->createElement('Taxpayer');
        $buyerTaxpayer->appendChild($dom->createElement('TIN', $custTin));
        $buyerTaxpayer->appendChild($dom->createElement('Name', (string) ($custSnapshot['legal_name'] ?? '')));
        $buyerTaxpayer->appendChild($dom->createElement('Address', (string) ($custSnapshot['legal_address'] ?? '')));
        $buyerTaxpayer->appendChild($dom->createElement('TinNotRequired', 'false'));
        $buyerTaxpayer->appendChild($dom->createElement('IsNatural', 'false'));
        $buyerInfo->appendChild($buyerTaxpayer);

        $deliveryLocation = (string) ($custSnapshot['delivery_address'] ?? $custSnapshot['legal_address'] ?? 'Հայաստան');
        $buyerInfo->appendChild($dom->createElement('DeliveryLocation', $deliveryLocation));

        // GoodsInfo
        $goodsInfo = $dom->createElement('GoodsInfo');
        $signableData->appendChild($goodsInfo);

        $totalTaxable = 0.00;
        $totalVat = 0.00;
        $grandTotal = 0.00;

        foreach ($document->items as $item) {
            $good = $dom->createElement('Good');
            $goodsInfo->appendChild($good);

            if ($item->good_parent_id) {
                $good->appendChild($dom->createElement('GoodParentId', (string) $item->good_parent_id));
            }

            $good->appendChild($dom->createElement('Description', (string) $item->description));
            $good->appendChild($dom->createElement('ClassifierCode', (string) $item->classifier_code));
            $good->appendChild($dom->createElement('Unit', (string) $item->unit));
            $good->appendChild($dom->createElement('Amount', (string) (float) $item->quantity));
            $good->appendChild($dom->createElement('PricePerUnit', (string) (float) $item->unit_price));
            $good->appendChild($dom->createElement('Price', (string) (float) $item->taxable_price));

            if ($item->discount_percent && (float) $item->discount_percent > 0) {
                $good->appendChild($dom->createElement('Discount', (string) (float) $item->discount_percent));
            }

            if ($item->deal_type) {
                $good->appendChild($dom->createElement('DealType', (string) $item->deal_type));
            }

            $vatRate = (float) ($item->vat_rate ?? 20.0);
            $vatAmount = (float) ($item->vat_amount ?? 0.0);
            $totalPrice = (float) $item->total_price;

            $good->appendChild($dom->createElement('VATRate', number_format($vatRate, 1, '.', '')));
            $good->appendChild($dom->createElement('VAT', (string) $vatAmount));
            $good->appendChild($dom->createElement('TotalPrice', (string) $totalPrice));

            $totalTaxable += (float) $item->taxable_price;
            $totalVat += $vatAmount;
            $grandTotal += $totalPrice;
        }

        $totalElement = $dom->createElement('Total');
        $totalElement->appendChild($dom->createElement('Price', (string) round($totalTaxable, 2)));
        $totalElement->appendChild($dom->createElement('VAT', (string) round($totalVat, 2)));
        $totalElement->appendChild($dom->createElement('TotalPrice', (string) round($grandTotal, 2)));
        $goodsInfo->appendChild($totalElement);

        // Signature
        $signature = $dom->createElement('Signature');
        $signature->appendChild($dom->createElement('SignatureType', 'PKCS7'));
        $signedData->appendChild($signature);

        // Metadata
        $meta = $dom->createElement('InvoiceMetadata');
        $meta->appendChild($dom->createElement('SubmissionDate', now()->format('Y-m-d\TH:i:s.uP')));
        $signedData->appendChild($meta);

        return (string) $dom->saveXML();
    }
}
