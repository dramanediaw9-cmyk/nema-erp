<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Access\Models\Role;
use App\Modules\Core\Audit\Models\ActivityLog;
use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\Tenant;
use App\Modules\Core\Dashboard\Models\UserNavigationFavorite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use BelongsToTenant, HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'name',
        'phone',
        'email',
        'password',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function navigationFavorites(): HasMany
    {
        return $this->hasMany(UserNavigationFavorite::class);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains(fn (Role $role) => $role->slug === $slug);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('platform_admin') || $this->hasRole('company_admin')) {
            return true;
        }

        return $this->roles
            ->loadMissing('permissions')
            ->flatMap(fn (Role $role) => $role->permissions)
            ->contains('slug', $permission);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function canAccessAllBranches(): bool
    {
        return $this->hasPermission('branches.cross_scope');
    }

    public function resolvedBranchScope(?int $requestedBranchId, ?int $workspaceBranchId): ?int
    {
        if ($this->canAccessAllBranches()) {
            return $requestedBranchId ?: null;
        }

        if ($this->branch_id) {
            return (int) $this->branch_id;
        }

        return $workspaceBranchId ?: $requestedBranchId;
    }
}
