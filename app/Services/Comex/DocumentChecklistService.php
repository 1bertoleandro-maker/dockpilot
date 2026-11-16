<?php

declare(strict_types=1);

namespace App\Services\Comex;

use App\Enums\DocumentType;
use App\Models\ComexProcess;

final class DocumentChecklistService
{
    /**
     * @return array{complete: bool, missing_labels: list<string>, items: list<array{type: DocumentType, label: string, present: bool, group_ok: bool}>}
     */
    public function summary(ComexProcess $process): array
    {
        $items = $this->items($process);

        $hasInvoice = false;
        $hasPl = false;
        $hasTransport = false;

        foreach ($items as $item) {
            if ($item['type'] === DocumentType::CommercialInvoice && $item['present']) {
                $hasInvoice = true;
            }
            if ($item['type'] === DocumentType::PackingList && $item['present']) {
                $hasPl = true;
            }
            if (in_array($item['type'], [DocumentType::BillOfLading, DocumentType::AirWaybill], true) && $item['present']) {
                $hasTransport = true;
            }
        }

        $missing = [];
        if (! $hasInvoice) {
            $missing[] = DocumentType::CommercialInvoice->label();
        }
        if (! $hasPl) {
            $missing[] = DocumentType::PackingList->label();
        }
        if (! $hasTransport) {
            $missing[] = 'BL ou AWB';
        }

        return [
            'complete' => $missing === [],
            'missing_labels' => $missing,
            'items' => $items,
        ];
    }

    /**
     * @return list<array{type: DocumentType, label: string, present: bool, group_ok: bool}>
     */
    public function items(ComexProcess $process): array
    {
        $process->loadMissing('documents');

        $present = $process->documents
            ->map(fn ($doc) => $doc->type instanceof DocumentType ? $doc->type : DocumentType::from((string) $doc->type))
            ->unique();

        $hasTransport = $present->contains(DocumentType::BillOfLading)
            || $present->contains(DocumentType::AirWaybill);

        $rows = [];

        foreach ([
            DocumentType::CommercialInvoice,
            DocumentType::PackingList,
            DocumentType::BillOfLading,
            DocumentType::AirWaybill,
        ] as $type) {
            $isPresent = $present->contains($type);
            $rows[] = [
                'type' => $type,
                'label' => $type->label(),
                'present' => $isPresent,
                'group_ok' => in_array($type, [DocumentType::BillOfLading, DocumentType::AirWaybill], true)
                    ? $hasTransport
                    : $isPresent,
            ];
        }

        return $rows;
    }
}
