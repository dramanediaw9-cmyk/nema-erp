<?php

namespace App\Modules\Commerce\Models;

use App\Models\User;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceChannelSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'commerce_channel_id',
        'snapshot_date',
        'gross_revenue',
        'orders_count',
        'average_order_value',
        'conversion_rate',
        'service_level',
        'failed_orders_count',
        'failed_payments_count',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'gross_revenue' => 'decimal:2',
            'orders_count' => 'integer',
            'average_order_value' => 'decimal:2',
            'conversion_rate' => 'decimal:2',
            'service_level' => 'decimal:2',
            'failed_orders_count' => 'integer',
            'failed_payments_count' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(CommerceChannel::class, 'commerce_channel_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
