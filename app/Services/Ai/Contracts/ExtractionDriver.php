<?php

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Models\Document;

interface ExtractionDriver
{
    /**
     * @return array{
     *     envelope: array<string, mixed>,
     *     model: string,
     *     raw_response_meta: array<string, mixed>
     * }
     */
    public function extract(Document $document): array;
}
