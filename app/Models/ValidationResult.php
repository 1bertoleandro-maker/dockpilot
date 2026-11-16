<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ValidationSeverity;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidationResult extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'process_id',
        'code',
        'severity',
        'message',
        'sources_json',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'severity' => ValidationSeverity::class,
            'sources_json' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ComexProcess, $this> */
    public function process(): BelongsTo
    {
        return $this->belongsTo(ComexProcess::class, 'process_id');
    }
}
