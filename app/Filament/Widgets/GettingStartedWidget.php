<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\ReviewQueue;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\ComexProcesses\ComexProcessResource;
use Filament\Widgets\Widget;

class GettingStartedWidget extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.getting-started';

    /**
     * @return array<string, string>
     */
    protected function getViewData(): array
    {
        return [
            'clientsUrl' => ClientResource::getUrl('create'),
            'processesUrl' => ComexProcessResource::getUrl('create'),
            'processesIndexUrl' => ComexProcessResource::getUrl('index'),
            'reviewUrl' => ReviewQueue::getUrl(),
        ];
    }
}
