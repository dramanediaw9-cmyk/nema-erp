<?php

namespace App\Modules\Sales\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesCreditNoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_credit_note_id','sales_invoice_item_id','product_id','description','qty','unit_price','line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function creditNote(): BelongsTo { return $this->belongsTo(SalesCreditNote::class, 'sales_credit_note_id'); }
    public function salesInvoiceItem(): BelongsTo { return $this->belongsTo(SalesInvoiceItem::class, 'sales_invoice_item_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
