<?php

declare(strict_types=1);

namespace App\Filament\Resources\ComexProcesses\RelationManagers;

use App\Enums\DocumentType;
use App\Models\ComexProcess;
use App\Models\User;
use App\Services\Documents\DocumentIntakeService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documentos';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (DocumentType $state): string => $state->label()),
                TextColumn::make('original_filename')
                    ->label('Arquivo')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof \App\Enums\DocumentStatus
                        ? $state->label()
                        : (string) $state),
                TextColumn::make('created_at')
                    ->label('Enviado em')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('upload')
                    ->label('Enviar documento')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Select::make('type')
                            ->label('Tipo de documento')
                            ->options(collect(DocumentType::cases())->mapWithKeys(
                                fn (DocumentType $type): array => [$type->value => $type->label()]
                            )->all())
                            ->required(),
                        FileUpload::make('file')
                            ->label('Arquivo PDF')
                            ->helperText('Invoice, Packing List, BL ou AWB em PDF.')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->maxSize(20480)
                            ->required()
                            ->storeFiles(false),
                    ])
                    ->action(function (array $data, DocumentIntakeService $intake): void {
                        /** @var ComexProcess $process */
                        $process = $this->getOwnerRecord();
                        /** @var User $user */
                        $user = auth()->user();

                        $uploaded = $data['file'] ?? null;
                        $file = $this->resolveUploadedFile($uploaded);

                        if ($file === null) {
                            Notification::make()
                                ->title('Arquivo inválido')
                                ->danger()
                                ->send();

                            return;
                        }

                        $intake->intake(
                            $process,
                            $file,
                            DocumentType::from($data['type']),
                            $user,
                        );

                        Notification::make()
                            ->title('Documento enviado')
                            ->body('Extração enfileirada (AI_DRIVER='.config('ai.driver').').')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('reextract')
                    ->label('Reprocessar IA')
                    ->icon('heroicon-o-sparkles')
                    ->requiresConfirmation()
                    ->action(function (\App\Models\Document $record): void {
                        \App\Jobs\ExtractDocumentJob::dispatchSync(
                            (int) $record->company_id,
                            (int) $record->id,
                        );

                        Notification::make()
                            ->title('Documento reprocessado')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Excluir'),
                ]),
            ]);
    }

    private function resolveUploadedFile(mixed $uploaded): ?UploadedFile
    {
        if ($uploaded instanceof UploadedFile) {
            return $uploaded;
        }

        if ($uploaded instanceof TemporaryUploadedFile) {
            return $uploaded;
        }

        if (is_string($uploaded) && $uploaded !== '') {
            $path = Storage::disk('local')->path($uploaded);

            if (is_file($path)) {
                return new UploadedFile($path, basename($path), null, null, true);
            }
        }

        if (is_array($uploaded)) {
            foreach ($uploaded as $item) {
                $resolved = $this->resolveUploadedFile($item);
                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        return null;
    }
}
