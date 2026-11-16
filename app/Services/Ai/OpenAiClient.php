<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class OpenAiClient
{
    private string $apiKey;

    private string $baseUrl;

    private int $timeout;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null, ?int $timeout = null)
    {
        $this->apiKey = (string) ($apiKey ?? config('ai.openai.api_key') ?? config('services.openai.key'));
        $this->baseUrl = rtrim((string) ($baseUrl ?? config('ai.openai.base_url') ?? config('services.openai.base_url')), '/');
        $this->timeout = $timeout ?? (int) config('ai.openai.timeout', 120);
    }

    /**
     * @return array{id: string, filename?: string, bytes?: int}
     */
    public function uploadFile(string $absolutePath, string $filename, string $mime = 'application/pdf'): array
    {
        $this->assertConfigured();

        if (! is_readable($absolutePath)) {
            throw new RuntimeException("OpenAI upload: file not readable [{$absolutePath}].");
        }

        $response = $this->http()
            ->attach('file', file_get_contents($absolutePath) ?: '', $filename, ['Content-Type' => $mime])
            ->post($this->baseUrl.'/files', [
                'purpose' => 'user_data',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'OpenAI file upload failed: HTTP '.$response->status().' '.$response->body()
            );
        }

        /** @var array{id?: string, filename?: string, bytes?: int} $json */
        $json = $response->json();

        if (empty($json['id'])) {
            throw new RuntimeException('OpenAI file upload response missing id.');
        }

        return [
            'id' => (string) $json['id'],
            'filename' => isset($json['filename']) ? (string) $json['filename'] : $filename,
            'bytes' => isset($json['bytes']) ? (int) $json['bytes'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $jsonSchema  OpenAI json_schema wrapper (name/strict/schema)
     * @return array{content: array<string, mixed>, meta: array<string, mixed>}
     */
    public function chatStructuredExtraction(
        string $model,
        string $fileId,
        string $userPrompt,
        array $jsonSchema,
    ): array {
        $this->assertConfigured();

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You extract structured foreign trade document fields. '
                        .'Return only JSON matching the schema. Missing values must be null. '
                        .'Dates must be YYYY-MM-DD when complete, otherwise null. Numbers must be numbers.',
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'file',
                            'file' => ['file_id' => $fileId],
                        ],
                        [
                            'type' => 'text',
                            'text' => $userPrompt,
                        ],
                    ],
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => $jsonSchema,
            ],
            'temperature' => 0,
        ];

        $response = $this->http()->post($this->baseUrl.'/chat/completions', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'OpenAI chat completion failed: HTTP '.$response->status().' '.$response->body()
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json();
        $content = data_get($json, 'choices.0.message.content');

        if (! is_string($content) || $content === '') {
            throw new RuntimeException('OpenAI response missing message content.');
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI response content is not valid JSON.');
        }

        return [
            'content' => $decoded,
            'meta' => [
                'id' => data_get($json, 'id'),
                'model' => data_get($json, 'model', $model),
                'usage' => data_get($json, 'usage'),
                'finish_reason' => data_get($json, 'choices.0.finish_reason'),
            ],
        ];
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout($this->timeout);
    }

    private function assertConfigured(): void
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }
    }
}
