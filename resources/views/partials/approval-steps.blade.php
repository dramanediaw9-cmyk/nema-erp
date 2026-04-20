@if ($approvalSteps->isNotEmpty())
    @php($nextApprovalStep = $approvalSteps->firstWhere('status', 'pending'))
    <div style="margin-top:18px; display:grid; gap:12px;">
        @foreach ($approvalSteps as $step)
            <div style="padding:12px 14px; border:1px solid #efe4d3; border-radius:12px; background:#fbf6ef;">
                <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start;">
                    <div>
                        <strong>Niveau {{ $step->step_order }} · {{ $step->label }}</strong>
                        <div class="muted" style="margin-top:6px;">
                            @if ($step->status === 'approved')
                                Validee par {{ $step->approver?->name ?? 'Systeme' }} le {{ $step->approved_at?->format('d/m/Y H:i') ?? 'N/A' }}
                            @else
                                En attente de validation
                                @if ($step->assignedApprover)
                                    · assignee a {{ $step->assignedApprover->name }}
                                @endif
                                @if ($step->due_at)
                                    · SLA {{ $step->due_at->format('d/m/Y H:i') }}
                                @endif
                                @if ($step->delegatedBy)
                                    · deleguee par {{ $step->delegatedBy->name }}
                                @endif
                            @endif
                        </div>
                    </div>
                    <span class="badge {{ $step->status === 'approved' ? 'badge-success' : ($step->isOverdue() ? 'badge-danger' : 'badge-warning') }}">
                        {{ $step->status === 'approved' ? 'Validee' : ($step->isOverdue() ? 'En retard' : 'En attente') }}
                    </span>
                </div>
                @if ($step->escalated_at)
                    <div class="help" style="margin-top:10px;">Escaladee le {{ $step->escalated_at->format('d/m/Y H:i') }}{{ $step->assignedApprover ? ' vers '.$step->assignedApprover->name : '' }}.</div>
                @endif
            </div>
        @endforeach

        @if ($nextApprovalStep)
            <div class="muted">Etape suivante requise : {{ $nextApprovalStep->label }}.</div>
        @endif
    </div>
@endif
