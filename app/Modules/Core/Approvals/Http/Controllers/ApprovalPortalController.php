<?php

namespace App\Modules\Core\Approvals\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Approvals\Services\ApprovalInboxService;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ApprovalPortalController extends Controller
{
    public function __construct(private readonly ApprovalInboxService $approvalInboxService)
    {
    }

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $module = $request->string('module')->value() ?: null;
        if (! in_array($module, ['sales', 'purchases', 'expenses'], true)) {
            $module = null;
        }

        $search = $request->string('search')->trim()->value() ?: null;
        $items = $this->approvalInboxService->pendingForUser($request->user(), $companyId, $module);

        if ($search) {
            $needle = Str::lower($search);
            $items = $items
                ->filter(function (array $item) use ($needle) {
                    return collect([
                        $item['module_label'] ?? null,
                        $item['number'] ?? null,
                        $item['counterpart'] ?? null,
                        $item['branch_name'] ?? null,
                        $item['creator_name'] ?? null,
                        $item['pending_step']?->label,
                    ])->filter()->contains(fn (?string $value) => Str::contains(Str::lower((string) $value), $needle));
                })
                ->values();
        }

        $summary = $this->approvalInboxService->summaryForUser($request->user(), $companyId);

        return view('approvals.index', [
            'items' => $items,
            'summary' => $summary,
            'filters' => [
                'module' => $module,
                'search' => $search,
            ],
        ]);
    }
}
