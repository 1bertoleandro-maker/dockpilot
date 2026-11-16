<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ComexProcess;
use App\Services\Comex\CrossValidationService;
use App\Support\CurrentCompany;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunProcessValidationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 30, 60];
    }

    public function __construct(
        public int $companyId,
        public int $processId,
    ) {}

    public function handle(CrossValidationService $service): void
    {
        CurrentCompany::set($this->companyId);

        try {
            /** @var ComexProcess $process */
            $process = ComexProcess::query()->findOrFail($this->processId);

            if ((int) $process->company_id !== $this->companyId) {
                throw new \RuntimeException('TENANT_MISMATCH: process company_id does not match job payload.');
            }

            $service->validate($process);
        } finally {
            CurrentCompany::clear();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('RunProcessValidationJob failed permanently', [
            'company_id' => $this->companyId,
            'process_id' => $this->processId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
