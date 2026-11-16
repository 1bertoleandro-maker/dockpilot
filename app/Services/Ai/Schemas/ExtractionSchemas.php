<?php

declare(strict_types=1);

namespace App\Services\Ai\Schemas;

use App\Enums\DocumentType;
use InvalidArgumentException;

final class ExtractionSchemas
{
    /**
     * OpenAI Structured Outputs JSON Schema (strict-compatible).
     *
     * @return array{name: string, strict: bool, schema: array<string, mixed>}
     */
    public static function forType(DocumentType $type): array
    {
        return match ($type) {
            DocumentType::CommercialInvoice => self::wrap('commercial_invoice', self::commercialInvoice()),
            DocumentType::PackingList => self::wrap('packing_list', self::packingList()),
            DocumentType::BillOfLading => self::wrap('bill_of_lading', self::billOfLading()),
            DocumentType::AirWaybill => self::wrap('air_waybill', self::airWaybill()),
        };
    }

    /**
     * Empty typed data with all required keys present as null / [].
     *
     * @return array<string, mixed>
     */
    public static function emptyData(DocumentType $type): array
    {
        return match ($type) {
            DocumentType::CommercialInvoice => [
                'invoice_number' => null,
                'invoice_date' => null,
                'currency' => null,
                'total_amount' => null,
                'incoterm' => null,
                'exporter' => self::emptyParty(),
                'importer' => self::emptyParty(),
                'items' => [],
            ],
            DocumentType::PackingList => [
                'reference' => null,
                'packing_date' => null,
                'total_packages' => null,
                'gross_weight_kg' => null,
                'net_weight_kg' => null,
                'volume_cbm' => null,
                'exporter_name' => null,
                'importer_name' => null,
                'items' => [],
            ],
            DocumentType::BillOfLading => [
                'bl_number' => null,
                'issue_date' => null,
                'shipper_name' => null,
                'consignee_name' => null,
                'notify_party' => null,
                'vessel' => null,
                'voyage' => null,
                'port_of_loading' => null,
                'port_of_discharge' => null,
                'total_packages' => null,
                'gross_weight_kg' => null,
                'measurement_cbm' => null,
                'freight_terms' => null,
                'container_numbers' => [],
            ],
            DocumentType::AirWaybill => [
                'awb_number' => null,
                'issue_date' => null,
                'shipper_name' => null,
                'consignee_name' => null,
                'airport_of_departure' => null,
                'airport_of_destination' => null,
                'total_packages' => null,
                'gross_weight_kg' => null,
                'chargeable_weight_kg' => null,
                'flight_number' => null,
            ],
        };
    }

    /**
     * @return list<string>
     */
    public static function requiredDataKeys(DocumentType $type): array
    {
        return array_keys(self::emptyData($type));
    }

    public static function fromString(string $type): DocumentType
    {
        $enum = DocumentType::tryFrom($type);

        if ($enum === null) {
            throw new InvalidArgumentException("Unsupported document_type [{$type}].");
        }

        return $enum;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array{name: string, strict: bool, schema: array<string, mixed>}
     */
    private static function wrap(string $name, array $schema): array
    {
        return [
            'name' => $name.'_extraction',
            'strict' => true,
            'schema' => $schema,
        ];
    }

    /** @return array<string, mixed> */
    private static function commercialInvoice(): array
    {
        $party = self::partySchema();
        $item = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'line' => ['type' => ['number', 'null']],
                'description' => ['type' => ['string', 'null']],
                'ncm' => ['type' => ['string', 'null']],
                'quantity' => ['type' => ['number', 'null']],
                'unit' => ['type' => ['string', 'null']],
                'unit_price' => ['type' => ['number', 'null']],
                'total_price' => ['type' => ['number', 'null']],
            ],
            'required' => ['line', 'description', 'ncm', 'quantity', 'unit', 'unit_price', 'total_price'],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'invoice_number' => ['type' => ['string', 'null']],
                'invoice_date' => ['type' => ['string', 'null']],
                'currency' => ['type' => ['string', 'null']],
                'total_amount' => ['type' => ['number', 'null']],
                'incoterm' => ['type' => ['string', 'null']],
                'exporter' => $party,
                'importer' => $party,
                'items' => ['type' => 'array', 'items' => $item],
            ],
            'required' => [
                'invoice_number', 'invoice_date', 'currency', 'total_amount', 'incoterm',
                'exporter', 'importer', 'items',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function packingList(): array
    {
        $item = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'description' => ['type' => ['string', 'null']],
                'quantity' => ['type' => ['number', 'null']],
                'unit' => ['type' => ['string', 'null']],
                'net_weight_kg' => ['type' => ['number', 'null']],
                'gross_weight_kg' => ['type' => ['number', 'null']],
                'packages' => ['type' => ['number', 'null']],
            ],
            'required' => ['description', 'quantity', 'unit', 'net_weight_kg', 'gross_weight_kg', 'packages'],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'reference' => ['type' => ['string', 'null']],
                'packing_date' => ['type' => ['string', 'null']],
                'total_packages' => ['type' => ['number', 'null']],
                'gross_weight_kg' => ['type' => ['number', 'null']],
                'net_weight_kg' => ['type' => ['number', 'null']],
                'volume_cbm' => ['type' => ['number', 'null']],
                'exporter_name' => ['type' => ['string', 'null']],
                'importer_name' => ['type' => ['string', 'null']],
                'items' => ['type' => 'array', 'items' => $item],
            ],
            'required' => [
                'reference', 'packing_date', 'total_packages', 'gross_weight_kg', 'net_weight_kg',
                'volume_cbm', 'exporter_name', 'importer_name', 'items',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function billOfLading(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'bl_number' => ['type' => ['string', 'null']],
                'issue_date' => ['type' => ['string', 'null']],
                'shipper_name' => ['type' => ['string', 'null']],
                'consignee_name' => ['type' => ['string', 'null']],
                'notify_party' => ['type' => ['string', 'null']],
                'vessel' => ['type' => ['string', 'null']],
                'voyage' => ['type' => ['string', 'null']],
                'port_of_loading' => ['type' => ['string', 'null']],
                'port_of_discharge' => ['type' => ['string', 'null']],
                'total_packages' => ['type' => ['number', 'null']],
                'gross_weight_kg' => ['type' => ['number', 'null']],
                'measurement_cbm' => ['type' => ['number', 'null']],
                'freight_terms' => ['type' => ['string', 'null']],
                'container_numbers' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => [
                'bl_number', 'issue_date', 'shipper_name', 'consignee_name', 'notify_party',
                'vessel', 'voyage', 'port_of_loading', 'port_of_discharge', 'total_packages',
                'gross_weight_kg', 'measurement_cbm', 'freight_terms', 'container_numbers',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function airWaybill(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'awb_number' => ['type' => ['string', 'null']],
                'issue_date' => ['type' => ['string', 'null']],
                'shipper_name' => ['type' => ['string', 'null']],
                'consignee_name' => ['type' => ['string', 'null']],
                'airport_of_departure' => ['type' => ['string', 'null']],
                'airport_of_destination' => ['type' => ['string', 'null']],
                'total_packages' => ['type' => ['number', 'null']],
                'gross_weight_kg' => ['type' => ['number', 'null']],
                'chargeable_weight_kg' => ['type' => ['number', 'null']],
                'flight_number' => ['type' => ['string', 'null']],
            ],
            'required' => [
                'awb_number', 'issue_date', 'shipper_name', 'consignee_name',
                'airport_of_departure', 'airport_of_destination', 'total_packages',
                'gross_weight_kg', 'chargeable_weight_kg', 'flight_number',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function partySchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'name' => ['type' => ['string', 'null']],
                'address' => ['type' => ['string', 'null']],
                'tax_id' => ['type' => ['string', 'null']],
                'country' => ['type' => ['string', 'null']],
            ],
            'required' => ['name', 'address', 'tax_id', 'country'],
        ];
    }

    /** @return array{name: null, address: null, tax_id: null, country: null} */
    private static function emptyParty(): array
    {
        return [
            'name' => null,
            'address' => null,
            'tax_id' => null,
            'country' => null,
        ];
    }
}
