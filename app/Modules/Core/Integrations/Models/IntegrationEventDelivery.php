<?php

namespace App\Modules\Core\Integrations\Models;

use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationEventDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration_event_id',
        'company_id',
        'channel',
        'target_url',
        'status',
        'attempt_number',
        'requested_at',
        'responded_at',
        'request_payload',
        'response_status',
        'response_headers',
        'response_body',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
            'request_payload' => 'array',
            'response_headers' => 'array',
            'attempt_number' => 'integer',
            'response_status' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(IntegrationEvent::class, 'integration_event_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
