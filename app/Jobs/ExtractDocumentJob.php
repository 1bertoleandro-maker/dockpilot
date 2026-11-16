<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ExtractionStatus;
use App\Models\Document;
use App\Services\Ai\DocumentExtractionService;
use App\Support\CurrentCompany;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExtractDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function __construct(
        public int $companyId,
        public int $documentId,
    ) {}

    public function handle(DocumentExtractionService $service): void
    {
        CurrentCompany::set($this->companyId);

        try {
            /** @var Document $document */
            $document = Document::query()->findOrFail($this->documentId);

            if ((int) $document->company_id !== $this->companyId) {
                throw new \RuntimeException('TENANT_MISMATCH: document company_id does not match job payload.');
            }

            $extraction = $service->extract($document);

            if ($extraction->status === ExtractionStatus::Succeeded) {
                RunProcessValidationJob::dispatch($this->companyId, (int) $document->process_id);
            }
        } finally {
            CurrentCompany::clear();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ExtractDocumentJob failed permanently', [
            'company_id' => $this->companyId,
            'document_id' => $this->documentId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
