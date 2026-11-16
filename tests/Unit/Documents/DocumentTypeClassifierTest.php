<?php

declare(strict_types=1);

namespace Tests\Unit\Documents;

use App\Enums\DocumentType;
use App\Services\Documents\DocumentTypeClassifier;
use PHPUnit\Framework\TestCase;

class DocumentTypeClassifierTest extends TestCase
{
    public function test_classifies_common_filenames(): void
    {
        $classifier = new DocumentTypeClassifier;

        $this->assertSame(DocumentType::CommercialInvoice, $classifier->classify('Commercial_Invoice_123.pdf'));
        $this->assertSame(DocumentType::PackingList, $classifier->classify('Packing_List_FINAL.pdf'));
        $this->assertSame(DocumentType::BillOfLading, $classifier->classify('Bill_of_Lading_MSC.pdf'));
        $this->assertSame(DocumentType::AirWaybill, $classifier->classify('AWB_LATAM.pdf'));
    }
}
