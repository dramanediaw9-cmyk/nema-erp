<?php

namespace App\Modules\Core\Notifications\Models;

use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'code',
        'type',
        'level',
        'title',
        'message',
        'action_url',
        'is_read',
        'read_at',
        'read_by',
        'resolved_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'resolved_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'read_by');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
