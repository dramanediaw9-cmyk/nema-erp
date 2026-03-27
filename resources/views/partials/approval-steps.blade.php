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
                            @endif
                        </div>
                    </div>
                    <span class="badge {{ $step->status === 'approved' ? 'badge-success' : 'badge-warning' }}">
                        {{ $step->status === 'approved' ? 'Validee' : 'En attente' }}
                    </span>
                </div>
            </div>
        @endforeach

        @if ($nextApprovalStep)
            <div class="muted">Etape suivante requise : {{ $nextApprovalStep->label }}.</div>
        @endif
    </div>
@endif
