<?php

namespace App\Modules\Core\Audit\Services;

use App\Modules\Core\Audit\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ActivityFeedService
{
    public function recentForSubjects(int $companyId, iterable $subjects, int $limit = 12): Collection
    {
        $subjectPairs = collect($subjects)
            ->filter(fn ($subject) => $subject instanceof Model && $subject->exists)
            ->map(fn (Model $subject): array => [
                'type' => $subject->getMorphClass(),
                'id' => $subject->getKey(),
            ])
            ->unique(fn (array $subject): string => $subject['type'].'#'.$subject['id'])
            ->values();

        if ($subjectPairs->isEmpty()) {
            return collect();
        }

        return ActivityLog::query()
            ->with(['user', 'branch'])
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($subjectPairs) {
                foreach ($subjectPairs as $subject) {
                    $query->orWhere(function (Builder $nested) use ($subject) {
                        $nested->where('subject_type', $subject['type'])
                            ->where('subject_id', $subject['id']);
                    });
                }
            })
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
