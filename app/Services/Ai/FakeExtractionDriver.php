<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Services\Ai\Contracts\ExtractionDriver;
use App\Services\Ai\Support\EnvelopeNormalizer;
use RuntimeException;

final class FakeExtractionDriver implements ExtractionDriver
{
    public function extract(Document $document): array
    {
        $type = $document->type;
        $path = rtrim((string) config('ai.fixtures_path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .$type->value
            .'.json';

        if (! is_readable($path)) {
            throw new RuntimeException("AI fake fixture not readable: {$path}");
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException("Unable to read AI fake fixture: {$path}");
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Invalid JSON in AI fake fixture: {$path}");
        }

        $model = 'fake-'.$type->value;
        $promptVersion = (string) config('ai.prompt_version');

        $envelope = EnvelopeNormalizer::normalize(
            $decoded,
            $type instanceof DocumentType ? $type : DocumentType::from((string) $type),
            $model,
            $promptVersion
        );

        return [
            'envelope' => $envelope,
            'model' => $model,
            'raw_response_meta' => [
                'driver' => 'fake',
                'fixture' => $type->value.'.json',
            ],
        ];
    }
}
