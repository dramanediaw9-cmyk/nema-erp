<?php

namespace App\Modules\Purchases\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasDocumentCollaboration;
use App\Models\User;
use App\Modules\Core\Approvals\Models\ApprovalStep;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\PaymentTerm;
use App\Modules\Core\Company\Models\PriceList;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use App\Modules\Treasury\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PurchaseBill extends Model
{
    use HasFactory;
    use BelongsToTenant;
    use HasDocumentCollaboration;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'warehouse_id',
        'supplier_id',
        'price_list_id',
        'payment_term_id',
        'purchase_order_id',
        'goods_receipt_id',
        'bill_number',
        'bill_date',
        'due_date',
        'status',
        'payment_status',
        'subtotal',
        'net_total',
        'tax_total',
        'total',
        'amount_paid',
        'balance_due',
        'notes',
        'validated_at',
        'approved_at',
        'approved_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'due_date' => 'date',
            'validated_at' => 'datetime',
            'approved_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'net_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseBillItem::class);
    }

    public function approvalSteps(): MorphMany
    {
        return $this->morphMany(ApprovalStep::class, 'approvable')->orderBy('step_order');
    }

    public function paymentAllocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function isSourcedFromReceipt(): bool
    {
        return (bool) $this->goods_receipt_id;
    }
}
