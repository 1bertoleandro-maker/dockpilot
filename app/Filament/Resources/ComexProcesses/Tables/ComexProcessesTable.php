<?php

declare(strict_types=1);

namespace App\Filament\Resources\ComexProcesses\Tables;

use App\Enums\ProcessStatus;
use App\Enums\ProcessType;
use App\Filament\Pages\ReviewProcess;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ComexProcessesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Referência')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof ProcessType
                        ? $state->label()
                        : (string) $state)
                    ->color(fn ($state): string => match ($state instanceof ProcessType ? $state : null) {
                        ProcessType::Import => 'info',
                        ProcessType::Export => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ProcessStatus $state): string => $state->label())
                    ->color(fn (ProcessStatus $state): string => match ($state) {
                        ProcessStatus::Draft => 'gray',
                        ProcessStatus::DocumentsPending => 'warning',
                        ProcessStatus::Extracting => 'info',
                        ProcessStatus::Review => 'warning',
                        ProcessStatus::Approved => 'success',
                        ProcessStatus::ReadyForSiscomex => 'success',
                    }),
                TextColumn::make('documents_count')
                    ->counts('documents')
                    ->label('Documentos'),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(ProcessStatus::cases())->mapWithKeys(
                        fn (ProcessStatus $status): array => [$status->value => $status->label()]
                    )->all()),
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(collect(ProcessType::cases())->mapWithKeys(
                        fn (ProcessType $type): array => [$type->value => $type->label()]
                    )->all()),
                SelectFilter::make('client_id')
                    ->label('Cliente')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Revisar')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->url(fn ($record): string => ReviewProcess::getUrl(['record' => $record]))
                    ->visible(fn ($record): bool => $record->status === ProcessStatus::Review),
                ViewAction::make()->label('Ver'),
                EditAction::make()->label('Editar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Excluir'),
                ]),
            ])
            ->emptyStateHeading('Nenhum processo ainda')
            ->emptyStateDescription('Crie um processo vinculado a um cliente e envie Invoice, Packing List e BL/AWB.')
            ->emptyStateIcon('heroicon-o-document-text');
    }
}
