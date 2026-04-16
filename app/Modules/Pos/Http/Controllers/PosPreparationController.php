<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pos\Models\PosPreparationDisplay;
use App\Modules\Pos\Models\PosPreparationTicket;
use App\Modules\Pos\Services\PosPreparationService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosPreparationController extends Controller
{
    public function __construct(
        private readonly PosPreparationService $preparationService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function index(CurrentWorkspace $workspace, Request $request): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        return view('pos.preparation.index', [
            'board' => $this->preparationService->board($companyId, $branchId, [
                'status' => $request->string('status')->value() ?: null,
                'printer_id' => $request->integer('printer_id') ?: null,
                'display_id' => $request->integer('display_id') ?: null,
            ]),
        ]);
    }

    public function display(PosPreparationDisplay $display, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);
        abort_if($companyId !== $display->company_id, 403);

        return view('pos.preparation.display', [
            'board' => $this->preparationService->displayBoard($companyId, $branchId, $display),
        ]);
    }

    public function updateStatus(PosPreparationTicket $ticket, CurrentWorkspace $workspace, Request $request): RedirectResponse
    {
        abort_if($workspace->companyId() !== $ticket->company_id, 403);

        $data = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $ticket = $this->preparationService->updateTicketStatus($ticket, $data['status'], $request->user()?->id);

        $this->activityLogger->log('pos.preparation.update', 'Mise a jour ticket preparation POS', $ticket, [
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status,
            'target_area' => $ticket->target_area,
        ]);

        return back()->with('success', 'Statut de preparation mis a jour avec succes.');
    }

    public function printTicket(PosPreparationTicket $ticket, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $ticket->company_id, 403);

        return view('pos.preparation.print', [
            'ticket' => $ticket->load(['items.product', 'printer', 'display', 'invoice.company', 'invoice.branch', 'invoice.customer', 'session', 'profile']),
        ]);
    }
}
