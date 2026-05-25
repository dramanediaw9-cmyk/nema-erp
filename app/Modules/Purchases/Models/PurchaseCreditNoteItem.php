<?php

namespace App\Modules\Purchases\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Models\TaxRule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseCreditNoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_credit_note_id',
        'purchase_bill_item_id',
        'product_id',
        'description',
        'qty',
        'unit_cost',
        'tax_rule_id',
        'tax_rate',
        'tax_amount',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(PurchaseCreditNote::class, 'purchase_credit_note_id');
    }

    public function purchaseBillItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseBillItem::class, 'purchase_bill_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function taxRule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class);
    }
}
