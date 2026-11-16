<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentType: string
{
    case CommercialInvoice = 'commercial_invoice';
    case PackingList = 'packing_list';
    case BillOfLading = 'bill_of_lading';
    case AirWaybill = 'air_waybill';

    public function label(): string
    {
        return match ($this) {
            self::CommercialInvoice => 'Commercial Invoice',
            self::PackingList => 'Packing List',
            self::BillOfLading => 'Bill of Lading (BL)',
            self::AirWaybill => 'Air Waybill (AWB)',
        };
    }
}
