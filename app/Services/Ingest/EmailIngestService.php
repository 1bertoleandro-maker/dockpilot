<?php

declare(strict_types=1);

namespace App\Services\Ingest;

use App\Enums\DocumentType;
use App\Enums\ProcessActivityEvent;
use App\Enums\ProcessStatus;
use App\Enums\ProcessType;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\ComexProcess;
use App\Models\Company;
use App\Models\User;
use App\Services\Comex\DocumentChecklistService;
use App\Services\Comex\ProcessActivityLogger;
use App\Services\Documents\DocumentIntakeService;
use App\Services\Documents\DocumentTypeClassifier;
use App\Services\Drive\DriveSyncService;
use App\Support\CurrentCompany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pipeline: caixa de entrada (arquivos) → processo → classifica → intake → checklist → Drive stub.
 * IMAP/Outlook real pluga aqui depois; hoje lê storage/app/inbound/{company_id}/
 */
final class EmailIngestService
{
    public function __construct(
        private readonly DocumentTypeClassifier $classifier,
        private readonly DocumentIntakeService $intake,
        private readonly DocumentChecklistService $checklist,
        private readonly ProcessActivityLogger $activity,
        private readonly DriveSyncService $drive,
    ) {}

    /**
     * @return array{fetched: int, created_processes: int, skipped: int, process_ids: list<int>}
     */
    public function poll(Company $company, ?User $actor = null): array
    {
        CurrentCompany::set((int) $company->id);

        try {
            $inbox = storage_path('app/inbound/'.$company->id);

            if (! is_dir($inbox)) {
                File::ensureDirectoryExists($inbox);

                return [
                    'fetched' => 0,
                    'created_processes' => 0,
                    'skipped' => 0,
                    'process_ids' => [],
                ];
            }

            $bundles = $this->discoverBundles($inbox);
            $created = 0;
            $fetched = 0;
            $skipped = 0;
            $processIds = [];

            $actor ??= User::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->whereIn('role', [UserRole::Admin->value, UserRole::Operator->value])
                ->orderBy('id')
                ->first();

            if ($actor === null) {
                throw new RuntimeException('No company staff user available for inbound intake.');
            }

            foreach ($bundles as $bundle) {
                $fetched += count($bundle['files']);

                try {
                    $process = $this->ingestBundle($company, $actor, $bundle);
                    $created++;
                    $processIds[] = (int) $process->id;

                    // Move processed files aside
                    $doneDir = $inbox.DIRECTORY_SEPARATOR.'_processed'.DIRECTORY_SEPARATOR.now()->format('Ymd_His').'_'.$process->id;
                    File::ensureDirectoryExists($doneDir);
                    foreach ($bundle['files'] as $filePath) {
                        @rename($filePath, $doneDir.DIRECTORY_SEPARATOR.basename($filePath));
                    }
                } catch (\Throwable $e) {
                    $skipped++;
                    Log::error('EmailIngestService bundle failed', [
                        'company_id' => $company->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'fetched' => $fetched,
                'created_processes' => $created,
                'skipped' => $skipped,
                'process_ids' => $processIds,
            ];
        } finally {
            CurrentCompany::clear();
        }
    }

    /**
     * Cria um bundle de demonstração e processa.
     *
     * @return array{fetched: int, created_processes: int, skipped: int, process_ids: list<int>}
     */
    public function simulateDemo(Company $company, Client $client, User $actor): array
    {
        CurrentCompany::set((int) $company->id);

        try {
            $inbox = storage_path('app/inbound/'.$company->id.'/demo_'.Str::lower(Str::random(6)));
            File::ensureDirectoryExists($inbox);

            $samples = [
                'Commercial_Invoice_DEMO.pdf' => "%PDF-1.4\n% Demo Commercial Invoice DockPilot\ntrailer\n%%EOF\n",
                'Packing_List_DEMO.pdf' => "%PDF-1.4\n% Demo Packing List DockPilot\ntrailer\n%%EOF\n",
                'Bill_of_Lading_DEMO.pdf' => "%PDF-1.4\n% Demo Bill of Lading DockPilot\ntrailer\n%%EOF\n",
            ];

            $files = [];
            foreach ($samples as $name => $content) {
                $path = $inbox.DIRECTORY_SEPARATOR.$name;
                file_put_contents($path, $content);
                $files[] = $path;
            }

            $meta = [
                'subject' => 'Docs importação '.$client->name,
                'client_id' => $client->id,
                'from' => $client->email,
            ];

            file_put_contents($inbox.DIRECTORY_SEPARATOR.'_meta.json', json_encode($meta, JSON_UNESCAPED_UNICODE));

            $process = $this->ingestBundle($company, $actor, [
                'dir' => $inbox,
                'files' => $files,
                'meta' => $meta,
            ]);

            $doneDir = storage_path('app/inbound/'.$company->id.'/_processed/'.now()->format('Ymd_His').'_'.$process->id);
            File::ensureDirectoryExists($doneDir);
            foreach ($files as $filePath) {
                if (is_file($filePath)) {
                    @rename($filePath, $doneDir.DIRECTORY_SEPARATOR.basename($filePath));
                }
            }

            return [
                'fetched' => count($files),
                'created_processes' => 1,
                'skipped' => 0,
                'process_ids' => [(int) $process->id],
            ];
        } finally {
            CurrentCompany::clear();
        }
    }

    /**
     * @param  array{dir: string, files: list<string>, meta: array<string, mixed>}  $bundle
     */
    private function ingestBundle(Company $company, User $actor, array $bundle): ComexProcess
    {
        $meta = $bundle['meta'];
        $client = $this->resolveClient($company, $meta);
        $reference = $this->buildReference($meta, $client);

        $process = ComexProcess::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'reference' => $reference,
            'type' => ProcessType::Import,
            'status' => ProcessStatus::DocumentsPending,
            'notes' => 'Aberto automaticamente via ingestão de documentos (e-mail/caixa de entrada).',
        ]);

        $this->activity->log(
            $process,
            ProcessActivityEvent::IngestFromEmail,
            'Ingestão automática',
            'Processo aberto a partir da caixa de entrada.',
            $actor,
            ['subject' => $meta['subject'] ?? null, 'from' => $meta['from'] ?? null]
        );

        $this->activity->log(
            $process,
            ProcessActivityEvent::ProcessCreated,
            'Processo criado',
            $reference,
            $actor
        );

        $folderHint = $this->drive->prepareProcessFolder($company, $client, $process);
        $this->activity->log(
            $process,
            ProcessActivityEvent::DriveFolderPrepared,
            'Pasta Drive preparada',
            $folderHint,
            $actor,
            ['path' => $folderHint]
        );

        foreach ($bundle['files'] as $path) {
            $filename = basename($path);
            $type = $this->classifier->classify($filename, (string) ($meta['subject'] ?? ''));

            if ($type === null) {
                $type = DocumentType::CommercialInvoice;
                $classifiedNote = 'Tipo não detectado — assumido Commercial Invoice para revisão.';
            } else {
                $classifiedNote = 'Classificado como '.$type->label();
            }

            $upload = new UploadedFile($path, $filename, 'application/pdf', null, true);
            $document = $this->intake->intake($process->fresh(), $upload, $type, $actor);

            $this->activity->log(
                $process,
                ProcessActivityEvent::DocumentClassified,
                'Documento classificado',
                $filename.' · '.$classifiedNote,
                $actor,
                ['document_id' => $document->id, 'type' => $type->value]
            );
        }

        $summary = $this->checklist->summary($process->fresh(['documents']));
        $this->activity->log(
            $process,
            ProcessActivityEvent::ChecklistUpdated,
            $summary['complete'] ? 'Checklist completo' : 'Checklist incompleto',
            $summary['complete']
                ? 'Invoice, Packing List e transporte presentes.'
                : 'Faltando: '.implode(', ', $summary['missing_labels']),
            $actor,
            $summary
        );

        return $process->fresh();
    }

    /**
     * @return list<array{dir: string, files: list<string>, meta: array<string, mixed>}>
     */
    private function discoverBundles(string $inbox): array
    {
        $bundles = [];

        // Root-level PDFs = one bundle
        $rootPdfs = collect(File::files($inbox))
            ->filter(fn ($f) => Str::lower($f->getExtension()) === 'pdf')
            ->map(fn ($f) => $f->getPathname())
            ->values()
            ->all();

        $rootMetaPath = $inbox.DIRECTORY_SEPARATOR.'_meta.json';
        $rootMeta = is_file($rootMetaPath)
            ? (json_decode((string) file_get_contents($rootMetaPath), true) ?: [])
            : [];

        if ($rootPdfs !== []) {
            $bundles[] = [
                'dir' => $inbox,
                'files' => $rootPdfs,
                'meta' => $rootMeta,
            ];
        }

        // Subdirs (except _processed)
        foreach (File::directories($inbox) as $dir) {
            if (Str::startsWith(basename($dir), '_')) {
                continue;
            }

            $pdfs = collect(File::files($dir))
                ->filter(fn ($f) => Str::lower($f->getExtension()) === 'pdf')
                ->map(fn ($f) => $f->getPathname())
                ->values()
                ->all();

            if ($pdfs === []) {
                continue;
            }

            $metaPath = $dir.DIRECTORY_SEPARATOR.'_meta.json';
            $meta = is_file($metaPath)
                ? (json_decode((string) file_get_contents($metaPath), true) ?: [])
                : ['subject' => basename($dir)];

            $bundles[] = [
                'dir' => $dir,
                'files' => $pdfs,
                'meta' => $meta,
            ];
        }

        return $bundles;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveClient(Company $company, array $meta): Client
    {
        if (! empty($meta['client_id'])) {
            $client = Client::query()
                ->where('company_id', $company->id)
                ->whereKey($meta['client_id'])
                ->first();
            if ($client) {
                return $client;
            }
        }

        if (! empty($meta['from'])) {
            $client = Client::query()
                ->where('company_id', $company->id)
                ->where('email', $meta['from'])
                ->first();
            if ($client) {
                return $client;
            }
        }

        return Client::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'document_number' => 'INBOUND-AUTO',
            ],
            [
                'name' => 'Cliente (ingestão automática)',
                'email' => $meta['from'] ?? null,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function buildReference(array $meta, Client $client): string
    {
        $slug = Str::upper(Str::slug(Str::limit((string) ($meta['subject'] ?? $client->name), 24, ''), '_'));
        $slug = $slug !== '' ? $slug : 'INBOUND';

        return sprintf('AUTO-%s-%s', now()->format('ymd'), $slug.'-'.Str::upper(Str::random(4)));
    }
}
