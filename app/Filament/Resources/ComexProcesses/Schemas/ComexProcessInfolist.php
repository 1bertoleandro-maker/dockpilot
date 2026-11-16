<?php

declare(strict_types=1);

namespace App\Filament\Resources\ComexProcesses\Schemas;

use App\Enums\ProcessStatus;
use App\Enums\ProcessType;
use App\Services\Comex\DocumentChecklistService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ComexProcessInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Processo')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('client.name')
                            ->label('Cliente'),
                        TextEntry::make('reference')
                            ->label('Referência'),
                        TextEntry::make('type')
                            ->label('Tipo')
                            ->badge()
                            ->formatStateUsing(fn (ProcessType $state): string => $state->label())
                            ->color(fn (ProcessType $state): string => match ($state) {
                                ProcessType::Import => 'info',
                                ProcessType::Export => 'success',
                            }),
                        TextEntry::make('status')
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
                        TextEntry::make('notes')
                            ->label('Observações')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Section::make('Checklist de documentos')
                    ->description('Mínimo para validação cruzada: Invoice + Packing List + (BL ou AWB).')
                    ->schema([
                        TextEntry::make('id')
                            ->label('Situação')
                            ->state(function ($record): string {
                                $summary = app(DocumentChecklistService::class)->summary($record);

                                if ($summary['complete']) {
                                    return 'Completo — todos os documentos obrigatórios enviados';
                                }

                                return 'Faltando: '.implode(', ', $summary['missing_labels']);
                            })
                            ->badge()
                            ->color(function ($record): string {
                                return app(DocumentChecklistService::class)->summary($record)['complete']
                                    ? 'success'
                                    : 'danger';
                            }),
                        TextEntry::make('checklist_detail')
                            ->label('Itens')
                            ->state(function ($record): string {
                                $items = app(DocumentChecklistService::class)->items($record);
                                $lines = [];

                                foreach ($items as $item) {
                                    $mark = $item['present'] ? '✓' : '○';
                                    $hint = '';
                                    if (in_array($item['type'], [\App\Enums\DocumentType::BillOfLading, \App\Enums\DocumentType::AirWaybill], true)) {
                                        $hint = $item['group_ok'] ? '' : ' (precisa BL ou AWB)';
                                    }
                                    $lines[] = "{$mark} {$item['label']}{$hint}";
                                }

                                return implode("\n", $lines);
                            })
                            ->markdown()
                            ->columnSpanFull(),
                    ]),
                Section::make('Auditoria')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Criado em')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label('Atualizado em')
                            ->dateTime(),
                    ]),
            ]);
    }
}
