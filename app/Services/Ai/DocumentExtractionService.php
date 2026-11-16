<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\DocumentStatus;
use App\Enums\ExtractionStatus;
use App\Models\Document;
use App\Models\Extraction;
use App\Services\Ai\Contracts\ExtractionDriver;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class DocumentExtractionService
{
    public function __construct(
        private readonly ExtractionDriver $driver,
    ) {}

    public function extract(Document $document): Extraction
    {
        $companyId = CurrentCompany::check();

        if ((int) $document->company_id !== $companyId) {
            throw new RuntimeException('TENANT_MISMATCH: document does not belong to current company.');
        }

        $disk = Storage::disk((string) config('ai.disk'));
        $path = (string) $document->storage_path;

        if ($path === '' || ! $disk->exists($path)) {
            throw new RuntimeException("Document storage_path not readable: {$path}");
        }

        if ($document->status !== DocumentStatus::QueuedAi) {
            $document->status = DocumentStatus::QueuedAi;
            $document->save();
        }

        $nextVersion = ((int) $document->extractions()->max('version')) + 1;
        $promptVersion = (string) config('ai.prompt_version');
        $placeholderModel = (string) config('ai.openai.model', 'gpt-4o');

        $extraction = Extraction::query()->create([
            'company_id' => $document->company_id,
            'document_id' => $document->id,
            'version' => $nextVersion,
            'model' => $placeholderModel,
            'prompt_version' => $promptVersion,
            'payload_json' => [],
            'confidence_json' => null,
            'status' => ExtractionStatus::Pending,
            'raw_response_meta' => null,
        ]);

        try {
            $result = $this->driver->extract($document);
            $envelope = $result['envelope'];

            $extraction->fill([
                'model' => $result['model'],
                'prompt_version' => $promptVersion,
                'payload_json' => $envelope,
                'confidence_json' => $envelope['field_confidence'] ?? [],
                'status' => ExtractionStatus::Succeeded,
                'raw_response_meta' => $result['raw_response_meta'],
            ]);
            $extraction->save();

            $document->status = DocumentStatus::Extracted;
            $document->save();

            return $extraction->fresh();
        } catch (Throwable $e) {
            Log::error('Document extraction failed', [
                'document_id' => $document->id,
                'company_id' => $document->company_id,
                'extraction_id' => $extraction->id,
                'error' => $e->getMessage(),
            ]);

            DB::transaction(function () use ($extraction, $document, $e): void {
                $extraction->status = ExtractionStatus::Failed;
                $extraction->raw_response_meta = [
                    'error' => $e->getMessage(),
                ];
                $extraction->payload_json = $extraction->payload_json ?: [
                    'contract_version' => config('ai.contract_version'),
                    'document_type' => $document->type->value,
                    'data' => [],
                    'field_confidence' => [],
                    'warnings' => [],
                    'meta' => ['error' => true],
                ];
                $extraction->save();

                $document->status = DocumentStatus::NeedsFix;
                $document->save();
            });

            throw $e;
        }
    }
}
