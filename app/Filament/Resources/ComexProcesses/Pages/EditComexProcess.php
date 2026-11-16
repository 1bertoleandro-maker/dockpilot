<?php

namespace App\Filament\Resources\ComexProcesses\Pages;

use App\Filament\Resources\ComexProcesses\ComexProcessResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditComexProcess extends EditRecord
{
    protected static string $resource = ComexProcessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
