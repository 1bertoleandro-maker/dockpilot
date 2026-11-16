<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProcessActivityEvent;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessActivity extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'process_id',
        'user_id',
        'event',
        'title',
        'description',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'event' => ProcessActivityEvent::class,
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<ComexProcess, $this> */
    public function process(): BelongsTo
    {
        return $this->belongsTo(ComexProcess::class, 'process_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
