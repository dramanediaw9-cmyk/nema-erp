<?php

namespace App\Modules\Pos\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Sales\Models\SalesInvoiceItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosPreparationTicketItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'preparation_ticket_id',
        'sales_invoice_item_id',
        'product_id',
        'description',
        'qty',
        'status',
        'combo_label',
        'menu_category_labels',
        'tag_labels',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'menu_category_labels' => 'array',
            'tag_labels' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(PosPreparationTicket::class, 'preparation_ticket_id');
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceItem::class, 'sales_invoice_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
