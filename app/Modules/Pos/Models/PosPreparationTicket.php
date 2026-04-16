<?php

namespace App\Modules\Pos\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosPreparationTicket extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'pos_session_id',
        'sales_invoice_id',
        'pos_profile_id',
        'printer_id',
        'display_id',
        'ticket_number',
        'target_area',
        'status',
        'priority',
        'target_minutes',
        'started_at',
        'ready_at',
        'served_at',
        'note_snapshot',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'target_minutes' => 'integer',
            'started_at' => 'datetime',
            'ready_at' => 'datetime',
            'served_at' => 'datetime',
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

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PosProfile::class, 'pos_profile_id');
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(PosPreparationPrinter::class, 'printer_id');
    }

    public function display(): BelongsTo
    {
        return $this->belongsTo(PosPreparationDisplay::class, 'display_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosPreparationTicketItem::class, 'preparation_ticket_id');
    }
}
