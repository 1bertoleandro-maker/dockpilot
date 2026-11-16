<?php

declare(strict_types=1);

namespace App\Services\Ai\Support;

use App\Enums\DocumentType;
use App\Services\Ai\Schemas\ExtractionSchemas;
use InvalidArgumentException;

final class EnvelopeNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     contract_version: string,
     *     document_type: string,
     *     data: array<string, mixed>,
     *     field_confidence: array<string, mixed>,
     *     warnings: list<array{code: string, message: string}>,
     *     meta: array<string, mixed>
     * }
     */
    public static function normalize(array $payload, DocumentType $expectedType, string $model, string $promptVersion): array
    {
        $contractVersion = (string) ($payload['contract_version'] ?? config('ai.contract_version'));
        $documentTypeRaw = (string) ($payload['document_type'] ?? $expectedType->value);

        if ($contractVersion !== (string) config('ai.contract_version')) {
            throw new InvalidArgumentException(
                "Invalid contract_version [{$contractVersion}], expected [".config('ai.contract_version').'].'
            );
        }

        if ($documentTypeRaw !== $expectedType->value) {
            throw new InvalidArgumentException(
                "Envelope document_type [{$documentTypeRaw}] does not match document [{$expectedType->value}]."
            );
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $data = array_replace_recursive(ExtractionSchemas::emptyData($expectedType), $data);

        foreach (ExtractionSchemas::requiredDataKeys($expectedType) as $key) {
            if (! array_key_exists($key, $data)) {
                throw new InvalidArgumentException("Missing required data key [{$key}].");
            }
        }

        $fieldConfidence = is_array($payload['field_confidence'] ?? null) ? $payload['field_confidence'] : [];
        $warnings = self::normalizeWarnings($payload['warnings'] ?? []);
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $meta['model'] = $meta['model'] ?? $model;
        $meta['prompt_version'] = $meta['prompt_version'] ?? $promptVersion;

        return [
            'contract_version' => $contractVersion,
            'document_type' => $expectedType->value,
            'data' => $data,
            'field_confidence' => $fieldConfidence,
            'warnings' => $warnings,
            'meta' => $meta,
        ];
    }

    /**
     * @param  mixed  $warnings
     * @return list<array{code: string, message: string}>
     */
    private static function normalizeWarnings(mixed $warnings): array
    {
        if (! is_array($warnings)) {
            return [];
        }

        $out = [];

        foreach ($warnings as $warning) {
            if (! is_array($warning)) {
                continue;
            }

            $out[] = [
                'code' => (string) ($warning['code'] ?? 'UNKNOWN'),
                'message' => (string) ($warning['message'] ?? ''),
            ];
        }

        return $out;
    }
}
