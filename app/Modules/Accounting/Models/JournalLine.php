<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Partners\Models\Partner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class JournalLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'partner_id',
        'label',
        'debit',
        'credit',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (JournalLine $line): void {
            if ($line->journalEntry?->posted_at) {
                throw new LogicException('Les lignes comptables postees sont immuables.');
            }
        });

        static::deleting(function (JournalLine $line): void {
            if ($line->journalEntry?->posted_at) {
                throw new LogicException('Les lignes comptables postees ne peuvent pas etre supprimees.');
            }
        });
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
