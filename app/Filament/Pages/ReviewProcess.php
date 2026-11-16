<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\ExtractionStatus;
use App\Enums\ProcessStatus;
use App\Enums\ValidationSeverity;
use App\Exceptions\DomainException;
use App\Models\ComexProcess;
use App\Models\Document;
use App\Models\Extraction;
use App\Models\User;
use App\Models\ValidationResult;
use App\Services\Comex\ProcessApprovalService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class ReviewProcess extends Page
{
    protected static string|BackedEnum|null $navigationIcon = null;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'review-process/{record}';

    protected static ?string $title = 'Revisão do processo';

    protected string $view = 'filament.pages.review-process';

    public ComexProcess $record;

    #[Url]
    public ?int $documentId = null;

    /** @var array<string, mixed> */
    public array $fieldValues = [];

    /** @var array<string, float|null> */
    public array $fieldConfidence = [];

    /** @var array<string, array<string, mixed>> */
    public array $overridesByDocument = [];

    public bool $acknowledgeWarns = false;

    public float $confidenceThreshold = 0.8;

    public function mount(int|string $record): void
    {
        $this->record = ComexProcess::query()
            ->with([
                'client',
                'documents.extractions',
                'validationResults',
            ])
            ->findOrFail($record);

        if ($this->documentId === null) {
            $this->documentId = $this->documents()->first()?->id;
        }

        $this->loadFieldsForSelectedDocument();
    }

    public function getHeading(): string
    {
        return 'Revisão · '.$this->record->reference;
    }

    public function getSubheading(): ?string
    {
        return $this->record->client?->name;
    }

    /**
     * @return Collection<int, Document>
     */
    public function documents(): Collection
    {
        return $this->record->documents->sortBy('id')->values();
    }

    /**
     * @return Collection<int, ValidationResult>
     */
    public function openValidations(): Collection
    {
        return $this->record->validationResults
            ->filter(fn (ValidationResult $result): bool => $result->resolved_at === null)
            ->sortBy(fn (ValidationResult $result): int => match ($result->severity) {
                ValidationSeverity::Fail => 0,
                ValidationSeverity::Warn => 1,
                default => 2,
            })
            ->values();
    }

    public function hasOpenFails(): bool
    {
        return $this->openValidations()
            ->contains(fn (ValidationResult $result): bool => $result->severity === ValidationSeverity::Fail);
    }

    public function hasOpenWarns(): bool
    {
        return $this->openValidations()
            ->contains(fn (ValidationResult $result): bool => $result->severity === ValidationSeverity::Warn);
    }

    public function canApprove(): bool
    {
        return $this->record->status === ProcessStatus::Review
            && ! $this->hasOpenFails()
            && (! $this->hasOpenWarns() || $this->acknowledgeWarns);
    }

    public function selectDocument(int $documentId): void
    {
        $this->persistCurrentDocumentOverrides();
        $this->documentId = $documentId;
        $this->loadFieldsForSelectedDocument();
    }

    public function documentPreviewUrl(): ?string
    {
        $document = $this->selectedDocument();

        if ($document === null) {
            return null;
        }

        return route('documents.preview', ['document' => $document]);
    }

    public function selectedDocument(): ?Document
    {
        return $this->documents()->firstWhere('id', $this->documentId);
    }

    public function isLowConfidence(string $key): bool
    {
        $confidence = $this->fieldConfidence[$key] ?? null;

        if ($confidence === null) {
            return true;
        }

        return $confidence < $this->confidenceThreshold;
    }

    public function approve(ProcessApprovalService $approvalService): void
    {
        if ($this->hasOpenFails()) {
                Notification::make()
                ->title('Aprovação bloqueada')
                ->body('Existem bloqueios abertos. Corrija os documentos e revalide.')
                ->danger()
                ->send();

            return;
        }

        if ($this->hasOpenWarns() && ! $this->acknowledgeWarns) {
            Notification::make()
                ->title('Confirme os alertas')
                ->body('Marque que está ciente dos alertas antes de aprovar.')
                ->warning()
                ->send();

            return;
        }

        $this->persistCurrentDocumentOverrides();

        /** @var User $user */
        $user = auth()->user();

        try {
            $approvalService->approve(
                $this->record->fresh(['documents.extractions', 'validationResults']),
                $user,
                $this->buildOperatorOverrides(),
                $this->acknowledgeWarns || ! $this->hasOpenWarns(),
            );
        } catch (DomainException $e) {
            Notification::make()
                ->title($e->errorCode)
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Processo aprovado')
            ->success()
            ->send();

        $this->redirect(ReviewQueue::getUrl());
    }

    private function loadFieldsForSelectedDocument(): void
    {
        $document = $this->selectedDocument();
        $this->fieldValues = [];
        $this->fieldConfidence = [];

        if ($document === null) {
            return;
        }

        $extraction = $this->latestSucceededExtraction($document);

        if ($extraction === null) {
            return;
        }

        $payload = $extraction->payload_json ?? [];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $confidence = is_array($extraction->confidence_json) ? $extraction->confidence_json : [];

        foreach (Arr::dot($data) as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $this->fieldValues[$key] = $value;
            $conf = data_get($confidence, $key);
            $this->fieldConfidence[$key] = is_numeric($conf) ? (float) $conf : null;
        }
    }

    private function persistCurrentDocumentOverrides(): void
    {
        // Kept in Livewire state per document via buildOperatorOverrides reading current fieldValues.
        // Multi-doc editing: we snapshot current values into session-like property.
        if ($this->documentId === null) {
            return;
        }

        $this->overridesByDocument[(string) $this->documentId] = $this->fieldValues;
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function buildOperatorOverrides(): array
    {
        $this->persistCurrentDocumentOverrides();

        $result = [];

        foreach ($this->documents() as $document) {
            $extraction = $this->latestSucceededExtraction($document);

            if ($extraction === null) {
                continue;
            }

            $payload = $extraction->payload_json ?? [];
            $original = is_array($payload['data'] ?? null) ? Arr::dot($payload['data']) : [];
            $current = $this->overridesByDocument[(string) $document->id] ?? [];

            $changed = [];

            foreach ($current as $key => $value) {
                $originalValue = $original[$key] ?? null;

                if ($this->normalizeComparable($originalValue) !== $this->normalizeComparable($value)) {
                    $changed[$key] = $this->castOverrideValue($value);
                }
            }

            if ($changed !== []) {
                $result[$document->id] = Arr::undot($changed);
            }
        }

        return $result;
    }

    private function latestSucceededExtraction(Document $document): ?Extraction
    {
        return $document->extractions
            ->where('status', ExtractionStatus::Succeeded)
            ->sortByDesc('version')
            ->first();
    }

    private function normalizeComparable(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private function castOverrideValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value) && ! str_contains((string) $value, ' ')) {
            return str_contains((string) $value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }
}
