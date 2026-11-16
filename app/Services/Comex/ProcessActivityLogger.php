<?php

declare(strict_types=1);

namespace App\Services\Comex;

use App\Enums\ProcessActivityEvent;
use App\Models\ComexProcess;
use App\Models\ProcessActivity;
use App\Models\User;
use App\Support\CurrentCompany;

final class ProcessActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function log(
        ComexProcess $process,
        ProcessActivityEvent $event,
        string $title,
        ?string $description = null,
        ?User $user = null,
        ?array $meta = null,
    ): ProcessActivity {
        return ProcessActivity::query()->create([
            'company_id' => $process->company_id ?: CurrentCompany::id(),
            'process_id' => $process->id,
            'user_id' => $user?->id,
            'event' => $event,
            'title' => $title,
            'description' => $description,
            'meta' => $meta,
        ]);
    }
}
