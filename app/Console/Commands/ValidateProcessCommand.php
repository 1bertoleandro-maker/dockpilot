<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RunProcessValidationJob;
use App\Models\ComexProcess;
use App\Services\Comex\CrossValidationService;
use App\Support\CurrentCompany;
use Illuminate\Console\Command;

class ValidateProcessCommand extends Command
{
    protected $signature = 'dockpilot:validate
                            {processId : Process ID}
                            {--queue : Dispatch RunProcessValidationJob instead of running sync}';

    protected $description = 'Run cross-document validation for a Comex process';

    public function handle(CrossValidationService $service): int
    {
        $processId = (int) $this->argument('processId');

        /** @var ComexProcess|null $process */
        $process = ComexProcess::withoutGlobalScopes()->find($processId);

        if ($process === null) {
            $this->error("Process [{$processId}] not found.");

            return self::FAILURE;
        }

        $companyId = (int) $process->company_id;

        if ($this->option('queue')) {
            RunProcessValidationJob::dispatch($companyId, $processId);
            $this->info("Queued RunProcessValidationJob for process {$processId} (company {$companyId}).");

            return self::SUCCESS;
        }

        CurrentCompany::set($companyId);

        try {
            $results = $service->validate($process->fresh());
            $this->info("Validation wrote {$results->count()} result(s).");

            foreach ($results as $result) {
                $this->line(sprintf(
                    ' - [%s] %s: %s',
                    $result->severity->value,
                    $result->code,
                    $result->message
                ));
            }

            return self::SUCCESS;
        } finally {
            CurrentCompany::clear();
        }
    }
}
