<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Início';

    protected static ?string $title = 'Central Comex';

    public function getColumns(): int|array
    {
        return 12;
    }
}
