<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use App\Services\Ingest\EmailIngestService;
use Illuminate\Console\Command;

class PollInboundEmailCommand extends Command
{
    protected $signature = 'dockpilot:poll-email {--company=} {--simulate : Gera PDFs demo e processa} {--client=}';

    protected $description = 'Processa caixa inbound (PDFs) ou simula ingestão por e-mail';

    public function handle(EmailIngestService $ingest): int
    {
        $query = Company::query();

        if ($id = $this->option('company')) {
            $query->whereKey($id);
        }

        foreach ($query->cursor() as $company) {
            if ($this->option('simulate')) {
                $client = $this->option('client')
                    ? Client::query()->where('company_id', $company->id)->whereKey($this->option('client'))->firstOrFail()
                    : Client::query()->where('company_id', $company->id)->orderBy('id')->first();

                if ($client === null) {
                    $this->warn("Company {$company->id}: sem cliente para simular.");

                    continue;
                }

                $actor = User::query()
                    ->where('company_id', $company->id)
                    ->whereIn('role', ['admin', 'operator'])
                    ->orderBy('id')
                    ->firstOrFail();

                $result = $ingest->simulateDemo($company, $client, $actor);
            } else {
                $result = $ingest->poll($company);
            }

            $this->line("Company {$company->id}: ".json_encode($result));
        }

        $this->info('Concluído.');

        return self::SUCCESS;
    }
}
