<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProcessStatus;
use App\Enums\ProcessType;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\ComexProcess;
use App\Models\Company;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->firstOrCreate(
            ['slug' => 'demo-despachante'],
            [
                'name' => 'Demo Despachante',
                'settings' => [
                    'validation' => [
                        'gross_weight_tolerance_pct' => 1.0,
                        'invoice_total_tolerance_pct' => 0.5,
                    ],
                ],
                'drive_folder_root' => null,
            ]
        );

        CurrentCompany::set((int) $company->id);

        try {
            User::query()->updateOrCreate(
                ['email' => 'admin@dockpilot.test'],
                [
                    'company_id' => $company->id,
                    'name' => 'Admin DockPilot',
                    'password' => Hash::make('password'),
                    'role' => UserRole::Admin,
                    'email_verified_at' => now(),
                ]
            );

            $client = Client::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'document_number' => '12.345.678/0001-90',
                ],
                [
                    'name' => 'Cliente Demo Importações',
                    'email' => 'cliente@demo.test',
                ]
            );

            User::query()->updateOrCreate(
                ['email' => 'portal@cliente-demo.test'],
                [
                    'company_id' => $company->id,
                    'client_id' => $client->id,
                    'name' => 'Portal Cliente Demo',
                    'password' => Hash::make('password'),
                    'role' => UserRole::Client,
                    'email_verified_at' => now(),
                ]
            );

            ComexProcess::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'reference' => 'DEMO-IMP-001',
                ],
                [
                    'client_id' => $client->id,
                    'type' => ProcessType::Import,
                    'status' => ProcessStatus::Draft,
                    'notes' => 'Processo demo pronto para upload de documentos.',
                ]
            );
        } finally {
            CurrentCompany::clear();
        }
    }
}
