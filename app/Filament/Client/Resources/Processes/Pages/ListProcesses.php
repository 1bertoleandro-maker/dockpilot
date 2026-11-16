<?php

declare(strict_types=1);

namespace App\Filament\Client\Resources\Processes\Pages;

use App\Filament\Client\Resources\Processes\ProcessResource;
use Filament\Resources\Pages\ListRecords;

class ListProcesses extends ListRecords
{
    protected static string $resource = ProcessResource::class;

    protected static ?string $title = 'Meus processos';
}
