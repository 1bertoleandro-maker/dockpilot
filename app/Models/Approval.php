<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'process_id',
        'approved_by',
        'approved_payload_json',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_payload_json' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ComexProcess, $this> */
    public function process(): BelongsTo
    {
        return $this->belongsTo(ComexProcess::class, 'process_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
