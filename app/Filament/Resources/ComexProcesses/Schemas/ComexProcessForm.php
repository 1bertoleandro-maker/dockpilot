<?php

declare(strict_types=1);

namespace App\Filament\Resources\ComexProcesses\Schemas;

use App\Enums\ProcessType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ComexProcessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->label('Cliente')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('reference')
                    ->label('Referência do processo')
                    ->placeholder('Ex.: IMP-2026-0142')
                    ->helperText('Código interno do despachante para este processo.')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Tipo')
                    ->options(collect(ProcessType::cases())->mapWithKeys(
                        fn (ProcessType $type): array => [$type->value => $type->label()]
                    )->all())
                    ->required(),
                Textarea::make('notes')
                    ->label('Observações')
                    ->placeholder('Informações internas do despacho...')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
