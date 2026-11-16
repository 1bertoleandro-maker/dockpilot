<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Enums\DocumentType;
use App\Services\Ai\Schemas\ExtractionSchemas;
use App\Services\Ai\Support\EnvelopeNormalizer;
use Tests\TestCase;

class EnvelopeNormalizerTest extends TestCase
{
    public function test_normalizes_partial_data_with_required_keys(): void
    {
        $envelope = EnvelopeNormalizer::normalize(
            [
                'contract_version' => 'ai.extract.v1',
                'document_type' => 'packing_list',
                'data' => [
                    'gross_weight_kg' => 100,
                    'total_packages' => 5,
                ],
            ],
            DocumentType::PackingList,
            'fake',
            'extract.v1'
        );

        $this->assertSame('ai.extract.v1', $envelope['contract_version']);
        $this->assertSame('packing_list', $envelope['document_type']);
        $this->assertSame(100, $envelope['data']['gross_weight_kg']);
        $this->assertArrayHasKey('reference', $envelope['data']);
        $this->assertNull($envelope['data']['reference']);
        $this->assertSame([], $envelope['data']['items']);
        $this->assertSame('extract.v1', $envelope['meta']['prompt_version']);
    }

    public function test_rejects_wrong_document_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EnvelopeNormalizer::normalize(
            [
                'contract_version' => 'ai.extract.v1',
                'document_type' => 'air_waybill',
                'data' => ExtractionSchemas::emptyData(DocumentType::AirWaybill),
            ],
            DocumentType::CommercialInvoice,
            'fake',
            'extract.v1'
        );
    }
}
