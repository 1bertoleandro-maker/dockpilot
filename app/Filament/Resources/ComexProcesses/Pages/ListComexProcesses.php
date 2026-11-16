<?php

namespace App\Filament\Resources\ComexProcesses\Pages;

use App\Filament\Resources\ComexProcesses\ComexProcessResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListComexProcesses extends ListRecords
{
    protected static string $resource = ComexProcessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
