<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\DriveStatus;
use App\Enums\ProcessActivityEvent;
use App\Enums\ProcessStatus;
use App\Jobs\ExtractDocumentJob;
use App\Jobs\SyncDocumentToDriveJob;
use App\Models\ComexProcess;
use App\Models\Document;
use App\Models\User;
use App\Services\Comex\ProcessActivityLogger;
use App\Support\CurrentCompany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class DocumentIntakeService
{
    public function __construct(
        private readonly ProcessActivityLogger $activity,
    ) {}

    public function intake(
        ComexProcess $process,
        UploadedFile $file,
        DocumentType $type,
        User $uploader,
    ): Document {
        $companyId = CurrentCompany::check();

        if ((int) $process->company_id !== $companyId) {
            throw new RuntimeException('TENANT_MISMATCH: process does not belong to current company.');
        }

        if ((int) $uploader->company_id !== $companyId) {
            throw new RuntimeException('TENANT_MISMATCH: uploader does not belong to current company.');
        }

        $diskName = (string) config('ai.disk', config('filesystems.default', 'local'));
        $disk = Storage::disk($diskName);

        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            ?: 'document';
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $relativePath = sprintf(
            'companies/%d/processes/%d/%s_%s.%s',
            $companyId,
            $process->id,
            $safeName,
            Str::lower(Str::random(8)),
            $extension
        );

        $storedPath = $disk->putFileAs(
            dirname($relativePath),
            $file,
            basename($relativePath)
        );

        if ($storedPath === false) {
            throw new RuntimeException('Failed to store uploaded document.');
        }

        $absolutePath = $disk->path($storedPath);
        $checksum = is_file($absolutePath)
            ? 'sha256:'.hash_file('sha256', $absolutePath)
            : null;

        return DB::transaction(function () use (
            $process,
            $type,
            $uploader,
            $companyId,
            $file,
            $storedPath,
            $checksum,
        ): Document {
            $document = Document::query()->create([
                'company_id' => $companyId,
                'process_id' => $process->id,
                'type' => $type,
                'original_filename' => $file->getClientOriginalName(),
                'storage_path' => $storedPath,
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
                'checksum' => $checksum,
                'status' => DocumentStatus::Uploaded,
                'drive_status' => DriveStatus::DrivePending,
                'uploaded_by' => $uploader->id,
            ]);

            $document->status = DocumentStatus::Stored;
            $document->save();

            if (in_array($process->status, [
                ProcessStatus::Draft,
                ProcessStatus::DocumentsPending,
                ProcessStatus::Review,
            ], true)) {
                $process->status = ProcessStatus::Extracting;
                $process->save();
            }

            SyncDocumentToDriveJob::dispatch($companyId, (int) $document->id);
            ExtractDocumentJob::dispatch($companyId, (int) $document->id);

            $this->activity->log(
                $process,
                ProcessActivityEvent::DocumentUploaded,
                'Documento recebido',
                $file->getClientOriginalName().' · '.$type->label(),
                $uploader,
                ['document_id' => $document->id, 'type' => $type->value]
            );

            return $document->fresh();
        });
    }
}
