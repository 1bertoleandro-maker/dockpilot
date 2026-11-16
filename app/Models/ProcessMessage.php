<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProcessMessageType;
use App\Enums\ProcessMessageVisibility;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessMessage extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'process_id',
        'user_id',
        'type',
        'visibility',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProcessMessageType::class,
            'visibility' => ProcessMessageVisibility::class,
        ];
    }

    /** @return BelongsTo<ComexProcess, $this> */
    public function process(): BelongsTo
    {
        return $this->belongsTo(ComexProcess::class, 'process_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
