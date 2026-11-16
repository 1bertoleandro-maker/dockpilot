<?php

declare(strict_types=1);

namespace App\Filament\Client\Widgets;

use App\Enums\ProcessStatus;
use App\Models\ComexProcess;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClientProcessOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $clientId = Filament::auth()->user()?->client_id;

        $base = ComexProcess::query()->where('client_id', $clientId);

        $open = (clone $base)->whereNotIn('status', [
            ProcessStatus::Approved,
            ProcessStatus::ReadyForSiscomex,
        ])->count();

        $review = (clone $base)->where('status', ProcessStatus::Review)->count();
        $approved = (clone $base)->where('status', ProcessStatus::Approved)->count();

        return [
            Stat::make('Em andamento', (string) $open)
                ->description('Processos ainda não aprovados')
                ->color('info'),
            Stat::make('Em revisão', (string) $review)
                ->description('Na conferência do despachante')
                ->color('warning'),
            Stat::make('Aprovados', (string) $approved)
                ->description('Conferência concluída')
                ->color('success'),
        ];
    }
}
