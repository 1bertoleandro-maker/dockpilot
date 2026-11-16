<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\Pages;

use App\Enums\ProcessStatus;
use App\Enums\ProcessType;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\ComexProcesses\ComexProcessResource;
use App\Models\Client;
use App\Models\ComexProcess;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newProcess')
                ->label('Novo processo')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->form([
                    TextInput::make('reference')
                        ->label('Referência do processo')
                        ->required()
                        ->maxLength(255),
                    Select::make('type')
                        ->label('Tipo')
                        ->options(collect(ProcessType::cases())->mapWithKeys(
                            fn (ProcessType $type): array => [$type->value => $type->label()]
                        )->all())
                        ->required()
                        ->default(ProcessType::Import->value),
                    Textarea::make('notes')
                        ->label('Observações')
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    /** @var Client $client */
                    $client = $this->getRecord();

                    $process = ComexProcess::query()->create([
                        'company_id' => $client->company_id,
                        'client_id' => $client->id,
                        'reference' => $data['reference'],
                        'type' => ProcessType::from($data['type']),
                        'status' => ProcessStatus::Draft,
                        'notes' => $data['notes'] ?? null,
                    ]);

                    Notification::make()
                        ->title('Processo criado')
                        ->success()
                        ->send();

                    $this->redirect(ComexProcessResource::getUrl('view', ['record' => $process]));
                }),
            EditAction::make()->label('Editar'),
        ];
    }
}
