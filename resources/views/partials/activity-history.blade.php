@php
    $activities = collect($activities ?? []);
    $title = $title ?? 'Historique des actions';
    $description = $description ?? 'Retrouve les dernieres actions utiles sur cette fiche sans passer par le journal technique complet.';
    $sectionId = $sectionId ?? null;
@endphp

<section class="card" @if ($sectionId) id="{{ $sectionId }}" @endif>
    <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:flex-start;">
        <div>
            <h2 style="margin:0;">{{ $title }}</h2>
            <div class="muted" style="margin-top:8px;">{{ $description }}</div>
        </div>
        @allowed('activity_logs.view')
            <a href="{{ route('activity-logs.index') }}" class="button button-secondary">Journal complet</a>
        @endallowed
    </div>

    <div style="margin-top:18px; display:grid; gap:14px;">
        @forelse ($activities as $activity)
            @php
                $subjectLabel = match ($activity->subject_type) {
                    \App\Modules\Sales\Models\SalesInvoice::class => 'Facture client',
                    \App\Modules\Purchases\Models\PurchaseBill::class => 'Facture fournisseur',
                    \App\Modules\Treasury\Models\Payment::class => 'Paiement',
                    \App\Modules\Partners\Models\Partner::class => 'Tiers',
                    \App\Modules\Catalog\Models\Product::class => 'Produit',
                    \App\Modules\Expenses\Models\Expense::class => 'Depense',
                    \App\Modules\Purchases\Models\GoodsReceipt::class => 'Reception',
                    \App\Modules\Purchases\Models\PurchaseOrder::class => 'Commande fournisseur',
                    \App\Modules\Sales\Models\SalesCreditNote::class => 'Avoir client',
                    default => class_basename((string) $activity->subject_type),
                };

                $meta = collect([
                    $activity->user?->name ?? 'Systeme',
                    $activity->branch?->name ? 'Agence '.$activity->branch->name : null,
                    $activity->ip_address ? 'IP '.$activity->ip_address : null,
                ])->filter()->implode(' · ');
            @endphp
            <div style="padding:14px 16px; border:1px solid #efe4d3; border-radius:16px; background:#fffaf3;">
                <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                        @include('partials.erp-status-badge', ['label' => $subjectLabel, 'tone' => 'muted'])
                        @include('partials.erp-status-badge', ['label' => $activity->action, 'tone' => 'muted'])
                    </div>
                    <div class="muted">{{ $activity->created_at?->format('d/m/Y H:i') ?? 'Horodatage indisponible' }}</div>
                </div>
                <div style="margin-top:10px; font-weight:600;">{{ $activity->description }}</div>
                <div class="help" style="margin-top:8px;">{{ $meta ?: 'Contexte utilisateur non renseigne' }}</div>
            </div>
        @empty
            <div class="empty-state" style="padding:8px 0 4px;">
                <h3>Aucune action recente sur cette fiche.</h3>
                <div class="muted">Les prochaines creations, validations, paiements ou corrections apparaitront ici.</div>
            </div>
        @endforelse
    </div>
</section>
