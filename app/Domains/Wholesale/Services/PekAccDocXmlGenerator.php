<?php

declare(strict_types=1);

namespace App\Domains\Wholesale\Services;

use App\Domains\Wholesale\Models\WholesaleTaxDocument;
use DOMDocument;

class PekAccDocXmlGenerator
{
    public const XMLNS = 'http://www.taxservice.am/tp3/invoice/definitions';

    public const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    public function generate(WholesaleTaxDocument $document): string
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS(self::XMLNS, 'ExportedAccDocData');
        $dom->appendChild($root);

        $signedData = $dom->createElement('SignedAccDocData');
        $root->appendChild($signedData);

        $data = $dom->createElement('Data');
        $signedData->appendChild($data);

        // SignableData
        $signableData = $dom->createElement('SignableData');
        $signableData->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::XSI);
        $signableData->setAttribute('Version', '1.0');
        $signableData->setAttributeNS(self::XSI, 'xsi:type', 'AccountingDocument');
        $data->appendChild($signableData);

        $user = $document->seller_snapshot['tin'] ?? 'wholesale';
        $signableData->appendChild($dom->createElement('User', (string) $user));
        $signableData->appendChild($dom->createElement('Type', '1'));
        $signableData->appendChild($dom->createElement('Traceable', 'false'));

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

        $deliveryDate = $document->supply_date ? $document->supply_date->format('Y-m-d').'+04:00' : now()->format('Y-m-d').'+04:00';
        $generalInfo->appendChild($dom->createElement('DeliveryDate', $deliveryDate));
        $generalInfo->appendChild($dom->createElement('Procedure', $document->is_adjustment ? '2' : '1'));
        $generalInfo->appendChild($dom->createElement('AdjustmentAccount', $document->is_adjustment ? 'true' : 'false'));

        // SupplierInfo
        $supplierInfo = $dom->createElement('SupplierInfo');
        $signableData->appendChild($supplierInfo);

        $sellerSnapshot = $document->seller_snapshot ?? [];
        $sellerTin = (string) ($sellerSnapshot['tin'] ?? '');

        $taxpayer = $dom->createElement('Taxpayer');
        $taxpayer->appendChild($dom->createElement('TIN', $sellerTin));
        $taxpayer->appendChild($dom->createElement('Name', (string) ($sellerSnapshot['legal_name'] ?? '')));
        $taxpayer->appendChild($dom->createElement('Address', (string) ($sellerSnapshot['legal_address'] ?? '')));

        // BankAccount is mandatory for Non-VAT / Accounting Documents
        $bankAcc = $dom->createElement('BankAccount');
        $bankName = ! empty($sellerSnapshot['bank_accounts'][0]['bank_name'])
            ? $sellerSnapshot['bank_accounts'][0]['bank_name']
            : 'ՀՀ Բանկ';
        $bankNum = ! empty($sellerSnapshot['bank_accounts'][0]['account_number'])
            ? $sellerSnapshot['bank_accounts'][0]['account_number']
            : '1570000000000000';
        $bankAcc->appendChild($dom->createElement('BankName', (string) $bankName));
        $bankAcc->appendChild($dom->createElement('BankAccountNumber', (string) $bankNum));
        $taxpayer->appendChild($bankAcc);

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

            $dealType = $item->deal_type ?: 'SPECIAL_TAX_SYSTEM';
            $good->appendChild($dom->createElement('DealType', (string) $dealType));

            $totalPrice = (float) $item->total_price;
            $good->appendChild($dom->createElement('TotalPrice', (string) $totalPrice));

            $grandTotal += $totalPrice;
        }

        $totalElement = $dom->createElement('Total');
        $totalElement->appendChild($dom->createElement('TotalPrice', (string) round($grandTotal, 2)));
        $goodsInfo->appendChild($totalElement);

        // Signature
        $signature = $dom->createElement('Signature');
        $signature->appendChild($dom->createElement('SignatureType', 'PKCS7'));
        $signedData->appendChild($signature);

        // Metadata
        $meta = $dom->createElement('AccDocMetadata');
        $meta->appendChild($dom->createElement('SubmissionDate', now()->format('Y-m-d\TH:i:s.uP')));
        $signedData->appendChild($meta);

        return (string) $dom->saveXML();
    }
}
