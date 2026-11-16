<?php

declare(strict_types=1);

namespace App\Filament\Client\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Meus processos';

    protected static ?string $title = 'Acompanhe seus processos';
}
