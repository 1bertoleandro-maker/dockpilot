<?php

declare(strict_types=1);

namespace App\Filament\Resources\ComexProcesses\RelationManagers;

use App\Enums\ProcessActivityEvent;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Linha do tempo';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Quando')
                    ->since()
                    ->sortable(),
                TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->formatStateUsing(fn (ProcessActivityEvent $state): string => $state->label()),
                TextColumn::make('title')
                    ->label('Título')
                    ->wrap(),
                TextColumn::make('description')
                    ->label('Detalhe')
                    ->wrap()
                    ->limit(80)
                    ->placeholder('—'),
                TextColumn::make('user.name')
                    ->label('Por')
                    ->placeholder('Sistema'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Sem eventos ainda')
            ->emptyStateDescription('Uploads, IA, validações e mensagens aparecem aqui.');
    }
}
