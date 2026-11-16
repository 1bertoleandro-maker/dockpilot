<?php

declare(strict_types=1);

namespace App\Filament\Resources\ComexProcesses\RelationManagers;

use App\Enums\ProcessMessageType;
use App\Enums\ProcessMessageVisibility;
use App\Models\ComexProcess;
use App\Models\ProcessMessage;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Observações e pedidos';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (ProcessMessageType $state): string => $state->label()),
                TextColumn::make('visibility')
                    ->label('Visibilidade')
                    ->badge()
                    ->formatStateUsing(fn (ProcessMessageVisibility $state): string => $state->label())
                    ->color(fn (ProcessMessageVisibility $state): string => match ($state) {
                        ProcessMessageVisibility::Internal => 'gray',
                        ProcessMessageVisibility::Client => 'info',
                    }),
                TextColumn::make('body')
                    ->label('Mensagem')
                    ->wrap()
                    ->limit(100),
                TextColumn::make('author.name')
                    ->label('Autor'),
                TextColumn::make('created_at')
                    ->label('Quando')
                    ->since(),
            ])
            ->headerActions([
                Action::make('add')
                    ->label('Registrar mensagem')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->form([
                        Select::make('type')
                            ->label('Tipo')
                            ->options(collect(ProcessMessageType::cases())->mapWithKeys(
                                fn (ProcessMessageType $type): array => [$type->value => $type->label()]
                            )->all())
                            ->required()
                            ->default(ProcessMessageType::Observation->value),
                        Select::make('visibility')
                            ->label('Visibilidade')
                            ->options(collect(ProcessMessageVisibility::cases())->mapWithKeys(
                                fn (ProcessMessageVisibility $v): array => [$v->value => $v->label()]
                            )->all())
                            ->required()
                            ->default(ProcessMessageVisibility::Client->value)
                            ->helperText('Interno = só a equipe. Cliente = aparece no portal.'),
                        Textarea::make('body')
                            ->label('Mensagem')
                            ->required()
                            ->rows(4),
                    ])
                    ->action(function (array $data): void {
                        /** @var ComexProcess $process */
                        $process = $this->getOwnerRecord();
                        /** @var User $user */
                        $user = auth()->user();

                        ProcessMessage::query()->create([
                            'company_id' => $process->company_id,
                            'process_id' => $process->id,
                            'user_id' => $user->id,
                            'type' => ProcessMessageType::from($data['type']),
                            'visibility' => ProcessMessageVisibility::from($data['visibility']),
                            'body' => $data['body'],
                        ]);

                        Notification::make()
                            ->title('Mensagem registrada')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Nenhuma interação ainda')
            ->emptyStateDescription('Use para anotar observações internas ou pedidos visíveis ao cliente.');
    }
}
