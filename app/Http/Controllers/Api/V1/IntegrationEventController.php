<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Core\Integrations\Models\IntegrationEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationEventController
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        $status = $request->string('status')->trim()->value();
        $eventName = $request->string('event_name')->trim()->value();
        $search = $request->string('search')->trim()->value();

        $events = IntegrationEvent::query()
            ->with(['company', 'latestDelivery'])
            ->where('company_id', $company->id)
            ->when(in_array($status, ['pending', 'published', 'failed'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($eventName !== '', fn (Builder $query) => $query->where('event_name', $eventName))
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('event_name', 'like', $like)
                        ->orWhere('aggregate_type', 'like', $like)
                        ->orWhere('aggregate_id', 'like', $like)
                        ->orWhere('last_error', 'like', $like);
                });
            })
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 200));

        return response()->json($events);
    }

    public function show(Request $request, IntegrationEvent $integrationEvent): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');
        abort_unless($integrationEvent->company_id === $company->id, 404);

        return response()->json($integrationEvent->load(['company', 'deliveries']));
    }
}
