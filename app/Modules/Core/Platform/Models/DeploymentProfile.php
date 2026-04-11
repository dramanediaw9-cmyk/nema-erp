<?php

namespace App\Modules\Core\Platform\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentProfile extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'owner_id',
        'commercial_offer',
        'deployment_mode',
        'lifecycle_stage',
        'hosting_target',
        'support_tier',
        'monitoring_level',
        'backup_strategy',
        'update_channel',
        'target_users',
        'target_branches',
        'go_live_target_at',
        'last_release_at',
        'last_backup_verified_at',
        'last_restore_drill_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'target_users' => 'integer',
            'target_branches' => 'integer',
            'go_live_target_at' => 'datetime',
            'last_release_at' => 'datetime',
            'last_backup_verified_at' => 'datetime',
            'last_restore_drill_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
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
