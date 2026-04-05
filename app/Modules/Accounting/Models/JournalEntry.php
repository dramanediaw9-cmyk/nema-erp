<?php

namespace App\Modules\Accounting\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class JournalEntry extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'journal_number',
        'journal_code',
        'status',
        'entry_date',
        'source_type',
        'source_id',
        'reference',
        'description',
        'posted_at',
        'immutable_hash',
        'is_reversal',
        'reverses_journal_entry_id',
        'reversal_reason',
        'total_debit',
        'total_credit',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'posted_at' => 'datetime',
            'is_reversal' => 'boolean',
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (JournalEntry $entry): void {
            if ($entry->posted_at) {
                throw new LogicException('Les journaux comptables postes sont immuables.');
            }
        });

        static::deleting(function (JournalEntry $entry): void {
            if ($entry->posted_at) {
                throw new LogicException('Les journaux comptables postes ne peuvent pas etre supprimes.');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_journal_entry_id');
    }

    public function reversalEntry(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_journal_entry_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
