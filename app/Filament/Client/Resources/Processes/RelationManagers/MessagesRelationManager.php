<?php

declare(strict_types=1);

namespace App\Filament\Client\Resources\Processes\RelationManagers;

use App\Enums\ProcessMessageType;
use App\Enums\ProcessMessageVisibility;
use App\Models\ComexProcess;
use App\Models\ProcessMessage;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Observações e pedidos';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('visibility', ProcessMessageVisibility::Client))
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (ProcessMessageType $state): string => $state->label()),
                TextColumn::make('body')
                    ->label('Mensagem')
                    ->wrap()
                    ->limit(120),
                TextColumn::make('author.name')
                    ->label('Autor'),
                TextColumn::make('created_at')
                    ->label('Quando')
                    ->since(),
            ])
            ->headerActions([
                Action::make('add')
                    ->label('Enviar mensagem')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->form([
                        Select::make('type')
                            ->label('Tipo')
                            ->options([
                                ProcessMessageType::Observation->value => ProcessMessageType::Observation->label(),
                                ProcessMessageType::Request->value => ProcessMessageType::Request->label(),
                            ])
                            ->required()
                            ->default(ProcessMessageType::Request->value),
                        Textarea::make('body')
                            ->label('Mensagem')
                            ->required()
                            ->rows(4)
                            ->placeholder('Ex.: Favor confirmar se o Packing List da fatura 5521 já foi recebido.'),
                    ])
                    ->action(function (array $data): void {
                        /** @var ComexProcess $process */
                        $process = $this->getOwnerRecord();
                        /** @var User $user */
                        $user = Filament::auth()->user();

                        ProcessMessage::query()->create([
                            'company_id' => $process->company_id,
                            'process_id' => $process->id,
                            'user_id' => $user->id,
                            'type' => ProcessMessageType::from($data['type']),
                            'visibility' => ProcessMessageVisibility::Client,
                            'body' => $data['body'],
                        ]);

                        Notification::make()
                            ->title('Mensagem enviada')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nenhuma mensagem ainda')
            ->emptyStateDescription('Envie uma observação ou pedido para a equipe do despachante.');
    }
}
