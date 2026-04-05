<?php

namespace App\Modules\Sales\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasDocumentCollaboration;
use App\Models\User;
use App\Modules\Collections\Models\CollectionFollowUp;
use App\Modules\Core\Approvals\Models\ApprovalStep;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\PaymentTerm;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Pos\Models\PosReturn;
use App\Modules\Pos\Models\PosSession;
use App\Modules\Treasury\Models\PaymentAllocation;
use App\Modules\Treasury\Models\PaymentGatewayCallback;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class SalesInvoice extends Model
{
    use HasFactory;
    use BelongsToTenant;
    use HasDocumentCollaboration;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'warehouse_id',
        'customer_id',
        'payment_term_id',
        'price_list_id',
        'origin_delivery_note_id',
        'sale_channel',
        'pos_session_id',
        'pos_sync_key',
        'invoice_number',
        'invoice_date',
        'due_date',
        'status',
        'payment_status',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_total',
        'net_total',
        'tax_total',
        'total',
        'amount_paid',
        'balance_due',
        'pos_cash_received',
        'pos_change_due',
        'stock_posted',
        'notes',
        'validated_at',
        'approved_at',
        'approved_by',
        'cancelled_at',
        'cancelled_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'validated_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'net_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'pos_cash_received' => 'decimal:2',
            'pos_change_due' => 'decimal:2',
            'stock_posted' => 'boolean',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'customer_id');
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function originDeliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class, 'origin_delivery_note_id');
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class);
    }

    public function posReturns(): HasMany
    {
        return $this->hasMany(PosReturn::class, 'sales_invoice_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(SalesCreditNote::class, 'sales_invoice_id');
    }

    public function approvalSteps(): MorphMany
    {
        return $this->morphMany(ApprovalStep::class, 'approvable')->orderBy('step_order');
    }

    public function paymentAllocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(CollectionFollowUp::class, 'sales_invoice_id');
    }

    public function portalActions(): MorphMany
    {
        return $this->morphMany(SalesPortalAction::class, 'actionable');
    }

    public function latestPortalAction(): MorphOne
    {
        return $this->morphOne(SalesPortalAction::class, 'actionable')->latestOfMany('signed_at');
    }

    public function paymentGatewayCallbacks(): HasMany
    {
        return $this->hasMany(PaymentGatewayCallback::class, 'sales_invoice_id');
    }

    public function latestPaymentGatewayCallback(): HasOne
    {
        return $this->hasOne(PaymentGatewayCallback::class, 'sales_invoice_id')->latestOfMany('received_at');
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function hasDiscount(): bool
    {
        return (float) $this->discount_total > 0;
    }
}





