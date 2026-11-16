<?php

declare(strict_types=1);

namespace App\Services\Drive;

use App\Enums\DriveStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\ComexProcess;
use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sync com Google Drive do despachante.
 * prepareProcessFolder já materializa caminho lógico; upload real via API depois.
 */
final class DriveSyncService
{
    public function prepareProcessFolder(Company $company, Client $client, ComexProcess $process): string
    {
        $root = trim((string) ($company->drive_folder_root ?: 'DockPilot'));
        $clientSlug = Str::slug($client->name) ?: 'cliente';
        $refSlug = Str::slug($process->reference) ?: ('processo-'.$process->id);

        $path = trim($root, '/').'/'.$clientSlug.'/'.$refSlug;

        Log::info('DriveSyncService.prepareProcessFolder', [
            'company_id' => $company->id,
            'process_id' => $process->id,
            'path' => $path,
        ]);

        return $path;
    }

    public function syncDocument(Document $document): void
    {
        $document->loadMissing(['company', 'process.client']);

        $folder = null;
        if ($document->company && $document->process?->client) {
            $folder = $this->prepareProcessFolder(
                $document->company,
                $document->process->client,
                $document->process
            );
        }

        Log::info('DriveSyncService.syncDocument stub', [
            'company_id' => $document->company_id,
            'document_id' => $document->id,
            'storage_path' => $document->storage_path,
            'drive_folder' => $folder,
        ]);

        // Stub “sucesso lógico” para demo local sem API Google.
        if ($document->drive_status !== DriveStatus::DriveSynced) {
            $document->drive_status = DriveStatus::DrivePending;
            $document->save();
        }
    }
}
