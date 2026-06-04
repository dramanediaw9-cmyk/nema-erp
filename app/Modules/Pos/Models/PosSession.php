<?php

namespace App\Modules\Pos\Models;

use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Treasury\Models\CashAccount;
use App\Modules\Treasury\Models\Payment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'cash_account_id',
        'session_number',
        'status',
        'opening_amount',
        'opening_cash_breakdown',
        'expected_amount',
        'expected_breakdown',
        'closing_amount',
        'counted_breakdown',
        'closing_cash_breakdown',
        'variance_amount',
        'variance_breakdown',
        'variance_notes',
        'opening_notes',
        'closing_notes',
        'opened_at',
        'closed_at',
        'opened_by',
        'closed_by',
        'unlocked_at',
        'unlocked_by',
        'unlock_reason',
    ];

    protected function casts(): array
    {
        return [
            'opening_amount' => 'decimal:2',
            'opening_cash_breakdown' => 'array',
            'expected_amount' => 'decimal:2',
            'closing_amount' => 'decimal:2',
            'counted_breakdown' => 'array',
            'closing_cash_breakdown' => 'array',
            'variance_amount' => 'decimal:2',
            'expected_breakdown' => 'array',
            'variance_breakdown' => 'array',
            'variance_notes' => 'array',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'unlocked_at' => 'datetime',
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function unlocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class, 'pos_session_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'pos_session_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PosReturn::class, 'pos_session_id');
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(PosDraft::class, 'pos_session_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isLocked(): bool
    {
        return ! $this->isOpen();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'open' => 'Ouverte',
            'closed' => 'Fermee / verrouillee',
            'locked' => 'Verrouillee',
            default => strtoupper((string) $this->status),
        };
    }
}
