<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Services\Ai\Contracts\ExtractionDriver;
use App\Services\Ai\Schemas\ExtractionSchemas;
use App\Services\Ai\Support\EnvelopeNormalizer;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class OpenAiExtractionDriver implements ExtractionDriver
{
    public function __construct(
        private readonly OpenAiClient $client,
    ) {}

    public function extract(Document $document): array
    {
        $type = $document->type instanceof DocumentType
            ? $document->type
            : DocumentType::from((string) $document->type);

        $disk = Storage::disk((string) config('ai.disk'));
        $relativePath = (string) $document->storage_path;

        if (! $disk->exists($relativePath)) {
            throw new RuntimeException("Document file not found on disk: {$relativePath}");
        }

        $absolutePath = $disk->path($relativePath);
        $model = (string) config('ai.openai.model', config('services.openai.model', 'gpt-4o'));
        $promptVersion = (string) config('ai.prompt_version');
        $schema = ExtractionSchemas::forType($type);

        $uploaded = $this->client->uploadFile(
            $absolutePath,
            $document->original_filename ?: basename($relativePath),
            $document->mime ?: 'application/pdf',
        );

        $prompt = sprintf(
            'Extract all fields for a %s document into the JSON schema. '
            .'Include every required key; use null when unknown.',
            $type->value
        );

        $result = $this->client->chatStructuredExtraction(
            $model,
            $uploaded['id'],
            $prompt,
            $schema,
        );

        $data = $result['content'];

        // Model may return typed data directly or a partial envelope.
        $payload = [
            'contract_version' => config('ai.contract_version'),
            'document_type' => $type->value,
            'data' => isset($data['data']) && is_array($data['data']) ? $data['data'] : $data,
            'field_confidence' => is_array($data['field_confidence'] ?? null) ? $data['field_confidence'] : [],
            'warnings' => is_array($data['warnings'] ?? null) ? $data['warnings'] : [],
            'meta' => [
                'model' => $model,
                'prompt_version' => $promptVersion,
            ],
        ];

        $envelope = EnvelopeNormalizer::normalize($payload, $type, $model, $promptVersion);

        return [
            'envelope' => $envelope,
            'model' => $model,
            'raw_response_meta' => [
                'driver' => 'openai',
                'file_id' => $uploaded['id'],
                'openai' => $result['meta'],
            ],
        ];
    }
}
