<?php

declare(strict_types=1);

namespace App\Services\Comex;

use App\Enums\DocumentStatus;
use App\Enums\ExtractionStatus;
use App\Enums\ProcessActivityEvent;
use App\Enums\ProcessStatus;
use App\Enums\ValidationSeverity;
use App\Exceptions\DomainException;
use App\Models\Approval;
use App\Models\ComexProcess;
use App\Models\Document;
use App\Models\Extraction;
use App\Models\User;
use App\Models\ValidationResult;
use App\Support\CurrentCompany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ProcessApprovalService
{
    public function __construct(
        private readonly ProcessActivityLogger $activity,
    ) {}

    /**
     * @param  array<int|string, array<string, mixed>>  $operatorOverrides  keyed by document_id
     */
    public function approve(
        ComexProcess $process,
        User $user,
        array $operatorOverrides = [],
        bool $acknowledgeWarns = true,
    ): Approval {
        $companyId = CurrentCompany::check();

        if ((int) $process->company_id !== $companyId) {
            throw new RuntimeException('TENANT_MISMATCH: process does not belong to current company.');
        }

        if ((int) $user->company_id !== $companyId) {
            throw new RuntimeException('TENANT_MISMATCH: user does not belong to current company.');
        }

        if ($process->status === ProcessStatus::Approved || $process->approval()->exists()) {
            throw new DomainException(
                'PROCESS_ALREADY_APPROVED',
                'Process is already approved.',
                409
            );
        }

        $openFails = ValidationResult::query()
            ->where('process_id', $process->id)
            ->where('severity', ValidationSeverity::Fail)
            ->whereNull('resolved_at')
            ->exists();

        if ($openFails) {
            throw new DomainException(
                'APPROVAL_BLOCKED_BY_FAIL',
                'Cannot approve while open validation fails remain.',
                422
            );
        }

        $process->load(['documents.extractions', 'validationResults']);

        $documentsPayload = [];
        $normalizedOverrides = [];

        foreach ($process->documents as $document) {
            $extraction = $this->latestSucceededExtraction($document);

            if ($extraction === null) {
                continue;
            }

            $payload = $extraction->payload_json ?? [];
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

            $docOverrides = $operatorOverrides[$document->id]
                ?? $operatorOverrides[(string) $document->id]
                ?? [];

            if (! is_array($docOverrides)) {
                $docOverrides = [];
            }

            if ($docOverrides !== []) {
                $normalizedOverrides[(string) $document->id] = $docOverrides;
                $data = $this->mergeOverrides($data, $docOverrides);
            }

            $documentsPayload[] = [
                'document_id' => $document->id,
                'document_type' => $document->type->value,
                'extraction_id' => $extraction->id,
                'data' => $data,
            ];
        }

        $validationSnapshot = $process->validationResults
            ->filter(fn (ValidationResult $result): bool => $result->resolved_at === null)
            ->map(function (ValidationResult $result) use ($acknowledgeWarns): array {
                $acknowledged = $result->severity === ValidationSeverity::Warn
                    ? $acknowledgeWarns
                    : false;

                return [
                    'code' => $result->code,
                    'severity' => $result->severity->value,
                    'acknowledged' => $acknowledged,
                ];
            })
            ->values()
            ->all();

        $approvedPayload = [
            'contract_version' => 'approval.v1',
            'process_id' => $process->id,
            'documents' => $documentsPayload,
            'operator_overrides' => $normalizedOverrides,
            'validation_snapshot' => $validationSnapshot,
        ];

        return DB::transaction(function () use (
            $process,
            $user,
            $companyId,
            $approvedPayload,
            $documentsPayload,
        ): Approval {
            $approval = Approval::query()->create([
                'company_id' => $companyId,
                'process_id' => $process->id,
                'approved_by' => $user->id,
                'approved_payload_json' => $approvedPayload,
                'approved_at' => now(),
            ]);

            $process->status = ProcessStatus::Approved;
            $process->save();

            $documentIds = collect($documentsPayload)->pluck('document_id')->all();

            if ($documentIds !== []) {
                Document::query()
                    ->whereIn('id', $documentIds)
                    ->update(['status' => DocumentStatus::Approved->value]);
            }

            ValidationResult::query()
                ->where('process_id', $process->id)
                ->whereNull('resolved_at')
                ->update(['resolved_at' => now()]);

            $this->activity->log(
                $process,
                ProcessActivityEvent::Approved,
                'Processo aprovado',
                'Conferência humana concluída.',
                $user,
            );

            return $approval->fresh();
        });
    }

    private function latestSucceededExtraction(Document $document): ?Extraction
    {
        return $document->extractions
            ->where('status', ExtractionStatus::Succeeded)
            ->sortByDesc('version')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mergeOverrides(array $data, array $overrides): array
    {
        foreach (Arr::dot($overrides) as $key => $value) {
            data_set($data, $key, $value);
        }

        return $data;
    }
}
