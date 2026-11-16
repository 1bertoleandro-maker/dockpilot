<?php

declare(strict_types=1);

namespace App\Filament\Client\Resources\Processes;

use App\Enums\ProcessStatus;
use App\Enums\ProcessType;
use App\Filament\Client\Resources\Processes\Pages\ListProcesses;
use App\Filament\Client\Resources\Processes\Pages\ViewProcess;
use App\Filament\Client\Resources\Processes\RelationManagers\MessagesRelationManager;
use App\Models\ComexProcess;
use App\Services\Comex\DocumentChecklistService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProcessResource extends Resource
{
    protected static ?string $model = ComexProcess::class;

    protected static ?string $recordTitleAttribute = 'reference';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Processos';

    protected static ?string $modelLabel = 'Processo';

    protected static ?string $pluralModelLabel = 'Processos';

    protected static ?string $slug = 'processes';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('client_id', Filament::auth()->user()?->client_id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Seu processo')
                ->columns(2)
                ->schema([
                    TextEntry::make('reference')->label('Referência'),
                    TextEntry::make('type')
                        ->label('Tipo')
                        ->badge()
                        ->formatStateUsing(fn (ProcessType $state): string => $state->label()),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (ProcessStatus $state): string => $state->label()),
                    TextEntry::make('documents_count')
                        ->label('Documentos')
                        ->state(fn (ComexProcess $record): int => $record->documents()->count()),
                    TextEntry::make('notes')
                        ->label('Observações do despachante')
                        ->placeholder('—')
                        ->columnSpanFull(),
                    TextEntry::make('updated_at')
                        ->label('Última atualização')
                        ->since(),
                ]),
            Section::make('Documentos do processo')
                ->schema([
                    TextEntry::make('checklist')
                        ->label('Checklist')
                        ->state(function (ComexProcess $record): string {
                            $summary = app(DocumentChecklistService::class)->summary($record);

                            return $summary['complete']
                                ? 'Completo — Invoice, Packing List e BL/AWB presentes'
                                : 'Faltando: '.implode(', ', $summary['missing_labels']);
                        })
                        ->badge()
                        ->color(fn (ComexProcess $record): string => app(DocumentChecklistService::class)->summary($record)['complete']
                            ? 'success'
                            : 'warning'),
                    TextEntry::make('documents_list')
                        ->label('Arquivos recebidos')
                        ->state(function (ComexProcess $record): string {
                            $docs = $record->documents;
                            if ($docs->isEmpty()) {
                                return 'Nenhum documento enviado ainda.';
                            }

                            return $docs->map(function ($doc) {
                                $type = $doc->type?->label() ?? 'Doc';

                                return '• '.$type.' — '.$doc->original_filename;
                            })->implode("\n");
                        })
                        ->markdown()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Referência')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (ProcessType $state): string => $state->label()),
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
                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (ComexProcess $record): string => ViewProcess::getUrl(['record' => $record]))
            ->emptyStateHeading('Nenhum processo ainda')
            ->emptyStateDescription('Quando o despachante abrir um processo para a sua empresa, ele aparece aqui.');
    }

    public static function getRelations(): array
    {
        return [
            MessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProcesses::route('/'),
            'view' => ViewProcess::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
