<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\DocumentType;
use Illuminate\Support\Str;

final class DocumentTypeClassifier
{
    public function classify(?string $filename, ?string $hint = null): ?DocumentType
    {
        $haystack = Str::lower(trim(($hint ?? '').' '.($filename ?? '')));

        if ($haystack === '') {
            return null;
        }

        $rules = [
            DocumentType::CommercialInvoice->value => [
                DocumentType::CommercialInvoice,
                ['commercial invoice', 'invoice', 'fatura comercial', 'fatura', 'nfe', 'nf-e'],
            ],
            DocumentType::PackingList->value => [
                DocumentType::PackingList,
                ['packing list', 'packinglist', 'romaneio', 'packing'],
            ],
            DocumentType::BillOfLading->value => [
                DocumentType::BillOfLading,
                ['bill of lading', 'bill_of_lading', 'conhecimento de embarque', 'conhecimento maritimo'],
            ],
            DocumentType::AirWaybill->value => [
                DocumentType::AirWaybill,
                ['air waybill', 'airwaybill', 'air way bill', 'conhecimento aereo', 'awb'],
            ],
        ];

        foreach ($rules as [$type, $needles]) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return $type;
                }
            }
        }

        // Shorter tokens after longer phrases to reduce false positives
        if (preg_match('/\bbl\b/', $haystack) === 1) {
            return DocumentType::BillOfLading;
        }

        return null;
    }
}
