<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DriveStatus;
use App\Models\Document;
use App\Services\Drive\DriveSyncService;
use App\Support\CurrentCompany;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncDocumentToDriveJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $companyId,
        public int $documentId,
    ) {}

    public function handle(DriveSyncService $drive): void
    {
        CurrentCompany::set($this->companyId);

        try {
            $document = Document::query()->with('company')->findOrFail($this->documentId);

            if ((int) $document->company_id !== $this->companyId) {
                throw new \RuntimeException('TENANT_MISMATCH');
            }

            if ($document->drive_status === DriveStatus::DriveSynced && filled($document->drive_file_id)) {
                return;
            }

            $drive->syncDocument($document);
        } finally {
            CurrentCompany::clear();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SyncDocumentToDriveJob failed', [
            'company_id' => $this->companyId,
            'document_id' => $this->documentId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
