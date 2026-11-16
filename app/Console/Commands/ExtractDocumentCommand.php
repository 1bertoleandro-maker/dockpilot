<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ExtractDocumentJob;
use App\Models\Document;
use App\Services\Ai\DocumentExtractionService;
use App\Support\CurrentCompany;
use Illuminate\Console\Command;

class ExtractDocumentCommand extends Command
{
    protected $signature = 'dockpilot:extract
                            {documentId : Document ID}
                            {--queue : Dispatch ExtractDocumentJob instead of running sync}';

    protected $description = 'Extract structured data from a document (AI_DRIVER=fake|openai)';

    public function handle(DocumentExtractionService $service): int
    {
        $documentId = (int) $this->argument('documentId');

        /** @var Document|null $document */
        $document = Document::withoutGlobalScopes()->find($documentId);

        if ($document === null) {
            $this->error("Document [{$documentId}] not found.");

            return self::FAILURE;
        }

        $companyId = (int) $document->company_id;

        if ($this->option('queue')) {
            ExtractDocumentJob::dispatch($companyId, $documentId);
            $this->info("Queued ExtractDocumentJob for document {$documentId} (company {$companyId}).");

            return self::SUCCESS;
        }

        CurrentCompany::set($companyId);

        try {
            $extraction = $service->extract($document->fresh());
            $this->info(sprintf(
                'Extraction v%d status=%s model=%s',
                $extraction->version,
                $extraction->status->value,
                $extraction->model
            ));

            return self::SUCCESS;
        } finally {
            CurrentCompany::clear();
        }
    }
}
