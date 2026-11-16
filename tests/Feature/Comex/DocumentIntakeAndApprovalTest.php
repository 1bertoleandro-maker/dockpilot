<?php

declare(strict_types=1);

namespace Tests\Feature\Comex;

use App\Enums\DocumentType;
use App\Enums\ProcessStatus;
use App\Enums\UserRole;
use App\Enums\ValidationSeverity;
use App\Exceptions\DomainException;
use App\Models\Client;
use App\Models\ComexProcess;
use App\Models\Company;
use App\Models\User;
use App\Models\ValidationResult;
use App\Services\Comex\ProcessApprovalService;
use App\Services\Documents\DocumentIntakeService;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentIntakeAndApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_intake_stores_document_and_dispatches_extraction(): void
    {
        Storage::fake((string) config('ai.disk', 'local'));

        $company = Company::factory()->create();
        CurrentCompany::set((int) $company->id);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
        ]);
        $client = Client::query()->create([
            'company_id' => $company->id,
            'name' => 'Cliente Teste',
        ]);
        $process = ComexProcess::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'reference' => 'TEST-1',
            'type' => 'import',
            'status' => ProcessStatus::Draft,
        ]);

        $file = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $document = app(DocumentIntakeService::class)->intake(
            $process,
            $file,
            DocumentType::CommercialInvoice,
            $user,
        );

        $this->assertSame('commercial_invoice', $document->type->value);
        $this->assertNotNull($document->storage_path);
        $this->assertSame(ProcessStatus::Review, $process->fresh()->status);
    }

    public function test_approval_blocked_by_open_fail(): void
    {
        $company = Company::factory()->create();
        CurrentCompany::set((int) $company->id);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
        ]);
        $client = Client::query()->create([
            'company_id' => $company->id,
            'name' => 'Cliente Teste',
        ]);
        $process = ComexProcess::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'reference' => 'TEST-2',
            'type' => 'import',
            'status' => ProcessStatus::Review,
        ]);

        ValidationResult::query()->create([
            'company_id' => $company->id,
            'process_id' => $process->id,
            'code' => 'MISSING_DOCUMENT',
            'severity' => ValidationSeverity::Fail,
            'message' => 'missing',
            'sources_json' => [],
        ]);

        $this->expectException(DomainException::class);

        app(ProcessApprovalService::class)->approve($process, $user, [], true);
    }
}
