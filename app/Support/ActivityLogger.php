<?php

namespace App\Support;

use App\Modules\Core\Audit\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public function log(string $action, string $description, ?Model $subject = null, array $properties = []): void
    {
        $user = Auth::user();

        ActivityLog::query()->create([
            'company_id' => session('current_company_id', $user?->company_id),
            'branch_id' => session('current_branch_id', $user?->branch_id),
            'user_id' => $user?->id,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties,
            'ip_address' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);
    }
}
