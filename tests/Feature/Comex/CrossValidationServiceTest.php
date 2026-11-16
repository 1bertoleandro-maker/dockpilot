<?php

declare(strict_types=1);

namespace Tests\Feature\Comex;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\ExtractionStatus;
use App\Enums\ProcessStatus;
use App\Enums\ProcessType;
use App\Enums\ValidationSeverity;
use App\Models\Client;
use App\Models\ComexProcess;
use App\Models\Company;
use App\Models\Document;
use App\Models\Extraction;
use App\Services\Comex\CrossValidationService;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_gross_weight_and_package_mismatches(): void
    {
        $company = Company::query()->create([
            'name' => 'Despachante Test',
            'slug' => 'despachante-test',
            'settings' => [
                'validation' => [
                    'gross_weight_tolerance_pct' => 1.0,
                    'invoice_total_tolerance_pct' => 0.5,
                ],
            ],
        ]);

        CurrentCompany::set((int) $company->id);

        $client = Client::query()->create([
            'company_id' => $company->id,
            'name' => 'Cliente',
        ]);

        $process = ComexProcess::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'reference' => 'IMP-001',
            'type' => ProcessType::Import,
            'status' => ProcessStatus::Extracting,
        ]);

        $this->seedExtraction($company->id, $process->id, DocumentType::CommercialInvoice, [
            'invoice_number' => 'INV-1',
            'invoice_date' => '2026-01-01',
            'currency' => 'USD',
            'total_amount' => 1000,
            'incoterm' => 'FOB',
            'exporter' => ['name' => 'ACME Export Ltd.', 'address' => null, 'tax_id' => null, 'country' => null],
            'importer' => ['name' => 'Brasil Importacoes SA', 'address' => null, 'tax_id' => null, 'country' => null],
            'items' => [
                ['line' => 1, 'description' => 'A', 'ncm' => null, 'quantity' => 1, 'unit' => 'PCS', 'unit_price' => 1000, 'total_price' => 1000],
            ],
        ]);

        $this->seedExtraction($company->id, $process->id, DocumentType::PackingList, [
            'reference' => 'PL-1',
            'packing_date' => '2026-01-02',
            'total_packages' => 10,
            'gross_weight_kg' => 1000,
            'net_weight_kg' => 900,
            'volume_cbm' => 1,
            'exporter_name' => 'ACME',
            'importer_name' => 'Brasil',
            'items' => [],
        ]);

        $this->seedExtraction($company->id, $process->id, DocumentType::BillOfLading, [
            'bl_number' => 'BL-1',
            'issue_date' => '2026-01-03',
            'shipper_name' => 'ACME Export Ltd',
            'consignee_name' => 'Brasil Importacoes S.A.',
            'notify_party' => null,
            'vessel' => null,
            'voyage' => null,
            'port_of_loading' => null,
            'port_of_discharge' => null,
            'total_packages' => 12,
            'gross_weight_kg' => 1100,
            'measurement_cbm' => null,
            'freight_terms' => null,
            'container_numbers' => [],
        ]);

        $results = app(CrossValidationService::class)->validate($process->fresh());

        $codes = $results->pluck('code')->all();

        $this->assertContains('GROSS_WEIGHT_MISMATCH', $codes);
        $this->assertContains('PACKAGE_COUNT_MISMATCH', $codes);
        $this->assertNotContains('MISSING_DOCUMENT', $codes);
        $this->assertTrue(
            $results->contains(fn ($r) => $r->code === 'GROSS_WEIGHT_MISMATCH' && $r->severity === ValidationSeverity::Fail)
        );

        $this->assertSame(ProcessStatus::Review, $process->fresh()->status);

        CurrentCompany::clear();
    }

    public function test_missing_document_fail_when_no_transport(): void
    {
        $company = Company::query()->create([
            'name' => 'Co',
            'slug' => 'co-missing',
            'settings' => null,
        ]);

        CurrentCompany::set((int) $company->id);

        $client = Client::query()->create([
            'company_id' => $company->id,
            'name' => 'Cliente',
        ]);

        $process = ComexProcess::query()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'reference' => 'IMP-002',
            'type' => ProcessType::Import,
            'status' => ProcessStatus::Extracting,
        ]);

        $this->seedExtraction($company->id, $process->id, DocumentType::CommercialInvoice, [
            'invoice_number' => 'INV-2',
            'invoice_date' => null,
            'currency' => 'USD',
            'total_amount' => 10,
            'incoterm' => null,
            'exporter' => ['name' => null, 'address' => null, 'tax_id' => null, 'country' => null],
            'importer' => ['name' => null, 'address' => null, 'tax_id' => null, 'country' => null],
            'items' => [],
        ]);

        $this->seedExtraction($company->id, $process->id, DocumentType::PackingList, [
            'reference' => null,
            'packing_date' => null,
            'total_packages' => 1,
            'gross_weight_kg' => 1,
            'net_weight_kg' => null,
            'volume_cbm' => null,
            'exporter_name' => null,
            'importer_name' => null,
            'items' => [],
        ]);

        $results = app(CrossValidationService::class)->validate($process->fresh());

        $this->assertTrue($results->contains(
            fn ($r) => $r->code === 'MISSING_DOCUMENT' && $r->severity === ValidationSeverity::Fail
        ));

        CurrentCompany::clear();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function seedExtraction(int $companyId, int $processId, DocumentType $type, array $data): void
    {
        $document = Document::query()->create([
            'company_id' => $companyId,
            'process_id' => $processId,
            'type' => $type,
            'original_filename' => $type->value.'.pdf',
            'storage_path' => 'tests/'.$type->value.'.pdf',
            'mime' => 'application/pdf',
            'checksum' => null,
            'status' => DocumentStatus::Extracted,
            'drive_status' => 'drive_pending',
            'uploaded_by' => null,
        ]);

        Extraction::query()->create([
            'company_id' => $companyId,
            'document_id' => $document->id,
            'version' => 1,
            'model' => 'fake',
            'prompt_version' => 'extract.v1',
            'payload_json' => [
                'contract_version' => 'ai.extract.v1',
                'document_type' => $type->value,
                'data' => $data,
                'field_confidence' => [],
                'warnings' => [],
                'meta' => ['model' => 'fake', 'prompt_version' => 'extract.v1'],
            ],
            'confidence_json' => [],
            'status' => ExtractionStatus::Succeeded,
            'raw_response_meta' => ['driver' => 'fake'],
        ]);
    }
}
