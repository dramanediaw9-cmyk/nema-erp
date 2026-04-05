<?php

namespace App\Modules\Core\Collaboration\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'attachable_type',
        'attachable_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    protected $appends = [
        'download_url',
        'human_size',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('documents.attachments.show', $this, false);
    }

    public function getHumanSizeAttribute(): string
    {
        $size = (int) $this->size_bytes;

        if ($size >= 1024 * 1024) {
            return number_format($size / (1024 * 1024), 1, ',', ' ').' Mo';
        }

        if ($size >= 1024) {
            return number_format($size / 1024, 0, ',', ' ').' Ko';
        }

        return $size.' o';
    }
}