<?php

declare(strict_types=1);

namespace App\Filament\Resources\ComexProcesses\Pages;

use App\Enums\ProcessStatus;
use App\Filament\Pages\ReviewProcess;
use App\Filament\Resources\ComexProcesses\ComexProcessResource;
use App\Jobs\ExtractDocumentJob;
use App\Jobs\RunProcessValidationJob;
use App\Models\ComexProcess;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewComexProcess extends ViewRecord
{
    protected static string $resource = ComexProcessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('review')
                ->label('Abrir revisão')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('warning')
                ->url(fn (): string => ReviewProcess::getUrl(['record' => $this->getRecord()]))
                ->visible(fn (): bool => $this->getRecord()->status === ProcessStatus::Review),
            Action::make('revalidate')
                ->label('Revalidar cruzado')
                ->icon('heroicon-o-arrows-right-left')
                ->requiresConfirmation()
                ->modalHeading('Rodar validação cruzada novamente?')
                ->modalDescription('Recalcula peso, volumes, parties e documentos faltantes com as últimas extrações.')
                ->action(function (): void {
                    /** @var ComexProcess $process */
                    $process = $this->getRecord();
                    RunProcessValidationJob::dispatchSync((int) $process->company_id, (int) $process->id);

                    Notification::make()
                        ->title('Validação atualizada')
                        ->success()
                        ->send();

                    $this->redirect(static::getUrl(['record' => $process]), navigate: true);
                }),
            Action::make('reextract')
                ->label('Reprocessar IA')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Reenviar documentos para a IA?')
                ->modalDescription('Gera nova versão de extração para cada documento do processo (AI_DRIVER atual).')
                ->action(function (): void {
                    /** @var ComexProcess $process */
                    $process = $this->getRecord()->load('documents');

                    if ($process->documents->isEmpty()) {
                        Notification::make()
                            ->title('Sem documentos')
                            ->body('Envie PDFs antes de reprocessar.')
                            ->warning()
                            ->send();

                        return;
                    }

                    foreach ($process->documents as $document) {
                        ExtractDocumentJob::dispatchSync((int) $process->company_id, (int) $document->id);
                    }

                    Notification::make()
                        ->title('Extração concluída')
                        ->body('Novas versões gravadas e validação disparada.')
                        ->success()
                        ->send();

                    $this->redirect(static::getUrl(['record' => $process]), navigate: true);
                }),
            EditAction::make()
                ->label('Editar'),
        ];
    }
}
