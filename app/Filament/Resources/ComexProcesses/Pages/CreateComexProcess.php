<?php

declare(strict_types=1);

namespace App\Filament\Resources\ComexProcesses\Pages;

use App\Enums\ProcessStatus;
use App\Filament\Resources\ComexProcesses\ComexProcessResource;
use Filament\Resources\Pages\CreateRecord;

class CreateComexProcess extends CreateRecord
{
    protected static string $resource = ComexProcessResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = ProcessStatus::Draft->value;

        return $data;
    }
}
