<?php

namespace App\Modules\Sales\Models;

use App\Models\User;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Partners\Models\Partner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesCreditNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','branch_id','warehouse_id','sales_invoice_id','customer_id','credit_note_number','credit_note_date','status','restock_items','subtotal','total','notes','validated_at','created_by',
    ];

    protected function casts(): array
    {
        return [
            'credit_note_date' => 'date',
            'validated_at' => 'datetime',
            'restock_items' => 'boolean',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Partner::class, 'customer_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany { return $this->hasMany(SalesCreditNoteItem::class); }
}
