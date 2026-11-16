<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\DriveStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'process_id',
        'type',
        'original_filename',
        'storage_path',
        'mime',
        'checksum',
        'status',
        'drive_file_id',
        'drive_status',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'drive_status' => DriveStatus::class,
        ];
    }

    /** @return BelongsTo<ComexProcess, $this> */
    public function process(): BelongsTo
    {
        return $this->belongsTo(ComexProcess::class, 'process_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return HasMany<Extraction, $this> */
    public function extractions(): HasMany
    {
        return $this->hasMany(Extraction::class);
    }
}
