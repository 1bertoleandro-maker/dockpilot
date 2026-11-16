<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\ProcessStatus;
use App\Enums\ValidationSeverity;
use App\Models\ComexProcess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class ReviewQueue extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Operação';

    protected static ?string $navigationLabel = 'Fila de revisão';

    protected static ?string $title = 'Fila de revisão';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'review-queue';

    protected string $view = 'filament.pages.review-queue';

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ComexProcess::query()
                    ->with(['client', 'validationResults'])
                    ->withCount([
                        'validationResults as fails_count' => fn ($q) => $q
                            ->where('severity', ValidationSeverity::Fail)
                            ->whereNull('resolved_at'),
                        'validationResults as warns_count' => fn ($q) => $q
                            ->where('severity', ValidationSeverity::Warn)
                            ->whereNull('resolved_at'),
                    ])
                    ->whereIn('status', [
                        ProcessStatus::Review,
                        ProcessStatus::Extracting,
                    ])
                    ->latest()
            )
            ->columns([
                TextColumn::make('reference')
                    ->label('Referência')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ProcessStatus $state): string => $state->label())
                    ->color(fn (ProcessStatus $state): string => match ($state) {
                        ProcessStatus::Extracting => 'info',
                        ProcessStatus::Review => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('fails_count')
                    ->label('Bloqueios')
                    ->badge()
                    ->color('danger'),
                TextColumn::make('warns_count')
                    ->label('Alertas')
                    ->badge()
                    ->color('warning'),
                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Abrir')
                    ->icon('heroicon-o-eye')
                    ->url(fn (ComexProcess $record): string => ReviewProcess::getUrl(['record' => $record]))
                    ->visible(fn (ComexProcess $record): bool => $record->status === ProcessStatus::Review),
            ])
            ->recordUrl(fn (ComexProcess $record): ?string => $record->status === ProcessStatus::Review
                ? ReviewProcess::getUrl(['record' => $record])
                : null)
            ->paginated([10, 25, 50]);
    }
}
