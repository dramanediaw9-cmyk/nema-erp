<?php

namespace App\Modules\Sales\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Models\TaxRule;
use App\Modules\Pos\Models\PosReturnItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_invoice_id',
        'product_id',
        'description',
        'qty',
        'unit_price',
        'line_subtotal',
        'discount_type',
        'discount_value',
        'discount_total',
        'tax_rule_id',
        'tax_rate',
        'tax_amount',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function hasDiscount(): bool
    {
        return (float) $this->discount_total > 0;
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function taxRule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class);
    }

    public function posReturnItems(): HasMany
    {
        return $this->hasMany(PosReturnItem::class, 'sales_invoice_item_id');
    }

    public function creditNoteItems(): HasMany
    {
        return $this->hasMany(SalesCreditNoteItem::class, 'sales_invoice_item_id');
    }
}
