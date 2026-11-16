<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ProcessStatus;
use App\Filament\Pages\ReviewQueue;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\ComexProcesses\ComexProcessResource;
use App\Models\Client;
use App\Models\ComexProcess;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $clients = Client::query()->count();
        $openProcesses = ComexProcess::query()
            ->whereNotIn('status', [ProcessStatus::Approved, ProcessStatus::ReadyForSiscomex])
            ->count();
        $inReview = ComexProcess::query()
            ->where('status', ProcessStatus::Review)
            ->count();
        $approved = ComexProcess::query()
            ->where('status', ProcessStatus::Approved)
            ->count();

        return [
            Stat::make('Clientes', (string) $clients)
                ->description('Importadores / exportadores cadastrados')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary')
                ->url(ClientResource::getUrl('index')),
            Stat::make('Processos abertos', (string) $openProcesses)
                ->description('Em andamento no despacho')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info')
                ->url(ComexProcessResource::getUrl('index')),
            Stat::make('Fila de revisão', (string) $inReview)
                ->description('Aguardando conferência humana')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color($inReview > 0 ? 'warning' : 'success')
                ->url(ReviewQueue::getUrl()),
            Stat::make('Aprovados', (string) $approved)
                ->description('Prontos para a próxima etapa')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
        ];
    }
}
