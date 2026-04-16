<?php

namespace App\Modules\Core\Automation\Models;

use App\Modules\Core\Notifications\Models\InternalNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'automation_rule_id',
        'notification_id',
        'signal_key',
        'status',
        'matched',
        'observed_value',
        'message',
        'payload',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'matched' => 'boolean',
            'observed_value' => 'integer',
            'payload' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(InternalNotification::class, 'notification_id');
    }
}
