<?php

namespace App\Modules\Core\Integrations\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IntegrationEvent extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'aggregate_type',
        'aggregate_id',
        'event_name',
        'payload',
        'status',
        'available_at',
        'published_at',
        'attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'available_at' => 'datetime',
            'published_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(IntegrationEventDelivery::class)->orderByDesc('attempt_number')->orderByDesc('id');
    }

    public function latestDelivery(): HasOne
    {
        return $this->hasOne(IntegrationEventDelivery::class)->latestOfMany('attempt_number');
    }

    public function incomingWebhooks(): HasMany
    {
        return $this->hasMany(IntegrationInboundWebhook::class)->orderByDesc('id');
    }

    public function latestIncomingWebhook(): HasOne
    {
        return $this->hasOne(IntegrationInboundWebhook::class)->latestOfMany('id');
    }
}
