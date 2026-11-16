<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExtractionStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Extraction extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'document_id',
        'version',
        'model',
        'prompt_version',
        'payload_json',
        'confidence_json',
        'status',
        'raw_response_meta',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'payload_json' => 'array',
            'confidence_json' => 'array',
            'raw_response_meta' => 'array',
            'status' => ExtractionStatus::class,
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
