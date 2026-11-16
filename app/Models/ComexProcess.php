<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProcessStatus;
use App\Enums\ProcessType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ComexProcess extends Model
{
    use BelongsToCompany;

    protected $table = 'processes';

    protected $fillable = [
        'company_id',
        'client_id',
        'reference',
        'type',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProcessType::class,
            'status' => ProcessStatus::class,
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'process_id');
    }

    /** @return HasMany<ValidationResult, $this> */
    public function validationResults(): HasMany
    {
        return $this->hasMany(ValidationResult::class, 'process_id');
    }

    /** @return HasOne<Approval, $this> */
    public function approval(): HasOne
    {
        return $this->hasOne(Approval::class, 'process_id');
    }

    /** @return HasMany<ProcessMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ProcessMessage::class, 'process_id')->latest();
    }

    /** @return HasMany<ProcessActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(ProcessActivity::class, 'process_id')->latest();
    }
}
