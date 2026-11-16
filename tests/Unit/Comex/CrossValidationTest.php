<?php

declare(strict_types=1);

namespace Tests\Unit\Comex;

use App\Services\Comex\Support\NameNormalizer;
use App\Services\Comex\Support\Tolerance;
use PHPUnit\Framework\TestCase;

class CrossValidationTest extends TestCase
{
    public function test_name_normalizer_strips_punctuation_and_case(): void
    {
        $this->assertSame(
            'ACME EXPORT LTD',
            NameNormalizer::normalize('Acme Export Ltd.')
        );

        $this->assertTrue(NameNormalizer::equals('ACME Export Ltd.', 'Acme Export Ltd'));
        $this->assertTrue(NameNormalizer::equals('Brasil Importacoes SA', 'Brasil Importacoes S.A.'));
        $this->assertFalse(NameNormalizer::equals('ACME', 'Other Co'));
    }

    public function test_name_normalizer_treats_empty_as_non_mismatch(): void
    {
        $this->assertTrue(NameNormalizer::equals(null, 'Anything'));
        $this->assertTrue(NameNormalizer::equals('Anything', null));
        $this->assertTrue(NameNormalizer::equals('', ''));
    }

    public function test_tolerance_percent_delta(): void
    {
        $this->assertNull(Tolerance::percentDelta(null, 10.0));
        $this->assertEqualsWithDelta(0.0, Tolerance::percentDelta(100.0, 100.0), 0.0001);
        // |100-101| / max(100,101) * 100 ≈ 0.9901
        $this->assertEqualsWithDelta(0.9901, Tolerance::percentDelta(100.0, 101.0), 0.001);
        $this->assertFalse(Tolerance::exceeds(1000.0, 1005.0, 1.0)); // ~0.5% < 1%
        $this->assertTrue(Tolerance::exceeds(1000.0, 1020.0, 1.0));
    }

    public function test_invoice_total_tolerance_half_percent(): void
    {
        $itemsSum = 1000.0;
        $total = 1006.0; // 0.597% vs max

        $this->assertTrue(Tolerance::exceeds($itemsSum, $total, 0.5));
        $this->assertFalse(Tolerance::exceeds(1000.0, 1004.0, 0.5));
    }
}
