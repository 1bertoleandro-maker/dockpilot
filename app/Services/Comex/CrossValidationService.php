<?php

declare(strict_types=1);

namespace App\Services\Comex;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\ExtractionStatus;
use App\Enums\ProcessStatus;
use App\Enums\ValidationSeverity;
use App\Models\ComexProcess;
use App\Models\Document;
use App\Models\Extraction;
use App\Models\ValidationResult;
use App\Services\Comex\Support\NameNormalizer;
use App\Services\Comex\Support\Tolerance;
use App\Support\CurrentCompany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CrossValidationService
{
    /**
     * @return Collection<int, ValidationResult>
     */
    public function validate(ComexProcess $process): Collection
    {
        $companyId = CurrentCompany::check();

        if ((int) $process->company_id !== $companyId) {
            throw new RuntimeException('TENANT_MISMATCH: process does not belong to current company.');
        }

        $process->load(['documents.extractions', 'company']);

        /** @var array<string, array{document: Document, extraction: Extraction, data: array<string, mixed>}> $byType */
        $byType = [];

        foreach ($process->documents as $document) {
            /** @var Extraction|null $extraction */
            $extraction = $document->extractions
                ->where('status', ExtractionStatus::Succeeded)
                ->sortByDesc('version')
                ->first();

            if ($extraction === null) {
                continue;
            }

            $payload = $extraction->payload_json ?? [];
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $typeKey = $document->type->value;

            $byType[$typeKey] = [
                'document' => $document,
                'extraction' => $extraction,
                'data' => $data,
            ];
        }

        $settings = is_array($process->company?->settings) ? $process->company->settings : [];
        $validationSettings = is_array($settings['validation'] ?? null) ? $settings['validation'] : [];
        $grossWeightTolerance = (float) ($validationSettings['gross_weight_tolerance_pct'] ?? 1.0);
        $invoiceTotalTolerance = (float) ($validationSettings['invoice_total_tolerance_pct'] ?? 0.5);

        $findings = [];

        $missing = $this->missingDocuments($byType);
        foreach ($missing as $finding) {
            $findings[] = $finding;
        }

        $pl = $byType[DocumentType::PackingList->value] ?? null;
        $bl = $byType[DocumentType::BillOfLading->value] ?? null;
        $awb = $byType[DocumentType::AirWaybill->value] ?? null;
        $invoice = $byType[DocumentType::CommercialInvoice->value] ?? null;

        foreach (array_filter([$bl, $awb]) as $transport) {
            if ($pl === null) {
                break;
            }

            $plWeight = $this->asFloat($pl['data']['gross_weight_kg'] ?? null);
            $trWeight = $this->asFloat($transport['data']['gross_weight_kg'] ?? null);

            if (Tolerance::exceeds($plWeight, $trWeight, $grossWeightTolerance)) {
                $findings[] = [
                    'code' => 'GROSS_WEIGHT_MISMATCH',
                    'severity' => ValidationSeverity::Fail,
                    'message' => sprintf(
                        'Gross weight mismatch: PL %.3f kg vs %s %.3f kg (tolerance %.2f%%).',
                        $plWeight,
                        $transport['document']->type->value,
                        $trWeight,
                        $grossWeightTolerance
                    ),
                    'sources_json' => [
                        'packing_list' => [
                            'document_id' => $pl['document']->id,
                            'extraction_id' => $pl['extraction']->id,
                            'gross_weight_kg' => $plWeight,
                        ],
                        'transport' => [
                            'document_id' => $transport['document']->id,
                            'document_type' => $transport['document']->type->value,
                            'extraction_id' => $transport['extraction']->id,
                            'gross_weight_kg' => $trWeight,
                        ],
                        'delta_pct' => Tolerance::percentDelta($plWeight, $trWeight),
                        'tolerance_pct' => $grossWeightTolerance,
                    ],
                ];
            }

            $plPackages = $this->asFloat($pl['data']['total_packages'] ?? null);
            $trPackages = $this->asFloat($transport['data']['total_packages'] ?? null);

            if ($plPackages !== null && $trPackages !== null && abs($plPackages - $trPackages) > 0.0001) {
                $findings[] = [
                    'code' => 'PACKAGE_COUNT_MISMATCH',
                    'severity' => ValidationSeverity::Fail,
                    'message' => sprintf(
                        'Package count mismatch: PL %.0f vs %s %.0f.',
                        $plPackages,
                        $transport['document']->type->value,
                        $trPackages
                    ),
                    'sources_json' => [
                        'packing_list' => [
                            'document_id' => $pl['document']->id,
                            'extraction_id' => $pl['extraction']->id,
                            'total_packages' => $plPackages,
                        ],
                        'transport' => [
                            'document_id' => $transport['document']->id,
                            'document_type' => $transport['document']->type->value,
                            'extraction_id' => $transport['extraction']->id,
                            'total_packages' => $trPackages,
                        ],
                    ],
                ];
            }
        }

        if ($invoice !== null) {
            foreach (array_filter([$bl, $awb]) as $transport) {
                $exporter = is_array($invoice['data']['exporter'] ?? null)
                    ? ($invoice['data']['exporter']['name'] ?? null)
                    : null;
                $importer = is_array($invoice['data']['importer'] ?? null)
                    ? ($invoice['data']['importer']['name'] ?? null)
                    : null;
                $shipper = $transport['data']['shipper_name'] ?? null;
                $consignee = $transport['data']['consignee_name'] ?? null;

                $partyMismatch = ! NameNormalizer::equals(
                    is_string($exporter) ? $exporter : null,
                    is_string($shipper) ? $shipper : null
                ) || ! NameNormalizer::equals(
                    is_string($importer) ? $importer : null,
                    is_string($consignee) ? $consignee : null
                );

                if ($partyMismatch) {
                    $findings[] = [
                        'code' => 'PARTY_NAME_MISMATCH',
                        'severity' => ValidationSeverity::Warn,
                        'message' => sprintf(
                            'Party names differ between commercial_invoice and %s.',
                            $transport['document']->type->value
                        ),
                        'sources_json' => [
                            'invoice' => [
                                'document_id' => $invoice['document']->id,
                                'extraction_id' => $invoice['extraction']->id,
                                'exporter_name' => $exporter,
                                'importer_name' => $importer,
                                'exporter_normalized' => NameNormalizer::normalize(is_string($exporter) ? $exporter : null),
                                'importer_normalized' => NameNormalizer::normalize(is_string($importer) ? $importer : null),
                            ],
                            'transport' => [
                                'document_id' => $transport['document']->id,
                                'document_type' => $transport['document']->type->value,
                                'extraction_id' => $transport['extraction']->id,
                                'shipper_name' => $shipper,
                                'consignee_name' => $consignee,
                                'shipper_normalized' => NameNormalizer::normalize(is_string($shipper) ? $shipper : null),
                                'consignee_normalized' => NameNormalizer::normalize(is_string($consignee) ? $consignee : null),
                            ],
                        ],
                    ];
                }
            }

            $items = is_array($invoice['data']['items'] ?? null) ? $invoice['data']['items'] : [];
            $itemsSum = 0.0;
            $hasItemTotal = false;

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $lineTotal = $this->asFloat($item['total_price'] ?? null);
                if ($lineTotal !== null) {
                    $itemsSum += $lineTotal;
                    $hasItemTotal = true;
                }
            }

            $invoiceTotal = $this->asFloat($invoice['data']['total_amount'] ?? null);

            if ($hasItemTotal && $invoiceTotal !== null
                && Tolerance::exceeds($itemsSum, $invoiceTotal, $invoiceTotalTolerance)) {
                $findings[] = [
                    'code' => 'INVOICE_TOTAL_INCONSISTENT',
                    'severity' => ValidationSeverity::Warn,
                    'message' => sprintf(
                        'Invoice items sum %.4f differs from total_amount %.4f (tolerance %.2f%%).',
                        $itemsSum,
                        $invoiceTotal,
                        $invoiceTotalTolerance
                    ),
                    'sources_json' => [
                        'document_id' => $invoice['document']->id,
                        'extraction_id' => $invoice['extraction']->id,
                        'items_sum' => $itemsSum,
                        'total_amount' => $invoiceTotal,
                        'delta_pct' => Tolerance::percentDelta($itemsSum, $invoiceTotal),
                        'tolerance_pct' => $invoiceTotalTolerance,
                    ],
                ];
            }
        }

        return DB::transaction(function () use ($process, $companyId, $findings, $byType): Collection {
            ValidationResult::query()
                ->where('process_id', $process->id)
                ->whereNull('resolved_at')
                ->delete();

            $created = collect();

            foreach ($findings as $finding) {
                $created->push(ValidationResult::query()->create([
                    'company_id' => $companyId,
                    'process_id' => $process->id,
                    'code' => $finding['code'],
                    'severity' => $finding['severity'],
                    'message' => $finding['message'],
                    'sources_json' => $finding['sources_json'],
                    'resolved_at' => null,
                ]));
            }

            foreach ($byType as $entry) {
                $document = $entry['document'];
                if ($document->status === DocumentStatus::Extracted) {
                    $document->status = DocumentStatus::ValidationDone;
                    $document->save();
                }
            }

            if ($process->status !== ProcessStatus::Approved) {
                $process->status = ProcessStatus::Review;
                $process->save();
            }

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $byType
     * @return list<array{code: string, severity: ValidationSeverity, message: string, sources_json: array<string, mixed>}>
     */
    private function missingDocuments(array $byType): array
    {
        $hasInvoice = isset($byType[DocumentType::CommercialInvoice->value]);
        $hasPl = isset($byType[DocumentType::PackingList->value]);
        $hasBl = isset($byType[DocumentType::BillOfLading->value]);
        $hasAwb = isset($byType[DocumentType::AirWaybill->value]);
        $hasTransport = $hasBl || $hasAwb;

        if ($hasInvoice && $hasPl && $hasTransport) {
            return [];
        }

        $missing = [];
        if (! $hasInvoice) {
            $missing[] = DocumentType::CommercialInvoice->value;
        }
        if (! $hasPl) {
            $missing[] = DocumentType::PackingList->value;
        }
        if (! $hasTransport) {
            $missing[] = 'bill_of_lading|air_waybill';
        }

        return [[
            'code' => 'MISSING_DOCUMENT',
            'severity' => ValidationSeverity::Fail,
            'message' => 'Required documents missing: '.implode(', ', $missing).'.',
            'sources_json' => [
                'present' => array_keys($byType),
                'missing' => $missing,
            ],
        ]];
    }

    private function asFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
