<?php

namespace App\Modules\Core\Dashboard\Services;

class ExecutiveBriefingService
{
    public function forDashboard(string $profileKey, array $stats, ?array $currentPeriodSummary, array $appMonitoring, ?array $onboarding = null): array
    {
        $items = [];

        if ((float) ($stats['reste_a_encaisser'] ?? 0) > 0) {
            $items[] = [
                'tone' => 'warning',
                'title' => 'Cash client a recuperer',
                'message' => $this->money((float) ($stats['reste_a_encaisser'] ?? 0)).' restent a encaisser sur '.number_format((float) ($stats['factures_impayees'] ?? 0), 0, ',', ' ').' facture(s) ouvertes.',
                'action_url' => route('sales.index', ['payment_status' => 'unpaid']),
                'action_label' => 'Ouvrir les impayes',
            ];
        }

        if ((int) ($stats['approbations_en_attente_total'] ?? 0) > 0) {
            $items[] = [
                'tone' => 'danger',
                'title' => 'Arbitrages en attente',
                'message' => number_format((float) ($stats['approbations_en_attente_total'] ?? 0), 0, ',', ' ').' document(s) attendent encore une validation avant impact metier complet.',
                'action_url' => route('approvals.index'),
                'action_label' => 'Traiter les validations',
            ];
        }

        if ((int) ($stats['alertes_stock'] ?? 0) > 0) {
            $items[] = [
                'tone' => 'warning',
                'title' => 'Stock a proteger',
                'message' => number_format((float) ($stats['alertes_stock'] ?? 0), 0, ',', ' ').' produit(s) sont deja au minimum ou en dessous sur le perimetre actif.',
                'action_url' => route('stock.index', ['stock_state' => 'low']),
                'action_label' => 'Voir le stock critique',
            ];
        }

        if ($currentPeriodSummary && ! ($currentPeriodSummary['period']?->isClosed()) && ! ($currentPeriodSummary['can_close'] ?? false)) {
            $items[] = [
                'tone' => 'danger',
                'title' => 'Cloture de periode bloquee',
                'message' => 'La periode '.$currentPeriodSummary['period']?->name.' ne peut pas etre cloturee tant que les bloqueurs courants ne sont pas leves.',
                'action_url' => route('accounting.periods.index'),
                'action_label' => 'Gerer la periode',
            ];
        }

        if (($appMonitoring['status'] ?? 'ok') !== 'ok') {
            $items[] = [
                'tone' => ($appMonitoring['status'] ?? 'warning') === 'fail' ? 'danger' : 'warning',
                'title' => 'Surveillance technique sous tension',
                'message' => ((int) data_get($appMonitoring, 'logs.signals_count', 0)).' signal(s) logs et '.((int) data_get($appMonitoring, 'failed_jobs.count', 0)).' job(s) en echec meritent une action Ops.',
                'action_url' => route('ops.index'),
                'action_label' => 'Ouvrir Ops',
            ];
        }

        if ($onboarding && ! ($onboarding['is_complete'] ?? true)) {
            $items[] = [
                'tone' => 'info',
                'title' => 'Pilote encore ameliorable',
                'message' => 'La mise en route couvre '.((int) ($onboarding['completed'] ?? 0)).'/'.((int) ($onboarding['total'] ?? 0)).' etape(s). Quelques prerequis restent a finaliser.',
                'action_url' => route('onboarding.index'),
                'action_label' => 'Voir la checklist',
            ];
        }

        $items = $this->rank($items);

        if ($items === []) {
            $items[] = [
                'tone' => 'success',
                'title' => 'Rythme d exploitation stable',
                'message' => 'Le cockpit ne remonte pas de tension immediate. Tu peux te concentrer sur la croissance et la qualite d execution.',
                'action_url' => route('reports.index'),
                'action_label' => 'Ouvrir les rapports',
            ];
        }

        return [
            'headline' => $this->dashboardHeadline($profileKey, $items),
            'summary' => collect($items)->pluck('title')->take(3)->implode(' | '),
            'items' => array_slice($items, 0, 4),
        ];
    }

    public function forReports(array $context): array
    {
        $sales = $context['sales'] ?? [];
        $comparison = $context['comparison'] ?? [];
        $grossMargin = $context['grossMargin'] ?? [];
        $receivables = $context['receivables'] ?? [];
        $payables = $context['payables'] ?? [];
        $stock = $context['stock'] ?? [];
        $supplierPerformance = $context['supplierPerformance'] ?? [];
        $filters = $context['filters'] ?? [];
        $canViewMargin = (bool) ($context['canViewMargin'] ?? false);
        $appMonitoring = $context['appMonitoring'] ?? ['status' => 'ok'];

        $items = [];
        $salesDelta = (float) data_get($comparison, 'sales.delta_percent', 0);
        $salesTotal = (float) ($sales['total'] ?? 0);

        if ($salesTotal > 0 && $salesDelta >= 10) {
            $items[] = [
                'tone' => 'success',
                'title' => 'Le chiffre d affaires accelere',
                'message' => 'Le CA progresse de '.number_format($salesDelta, 1, ',', ' ').' % sur la periode analysee.',
                'action_url' => route('reports.index', $filters),
                'action_label' => 'Relire la periode',
            ];
        } elseif ($salesTotal > 0 && $salesDelta <= -10) {
            $items[] = [
                'tone' => 'danger',
                'title' => 'Le chiffre d affaires ralentit',
                'message' => 'Le CA recule de '.number_format(abs($salesDelta), 1, ',', ' ').' % par rapport a la periode precedente.',
                'action_url' => route('sales.index', $filters),
                'action_label' => 'Analyser les ventes',
            ];
        }

        if ($canViewMargin && $salesTotal > 0 && (float) ($grossMargin['rate'] ?? 0) < 20) {
            $items[] = [
                'tone' => 'warning',
                'title' => 'Marge estimee a defendre',
                'message' => 'La marge estimee descend a '.number_format((float) ($grossMargin['rate'] ?? 0), 1, ',', ' ').' % du chiffre d affaires.',
                'action_url' => route('reports.index', $filters),
                'action_label' => 'Creuser la marge',
            ];
        }

        if ($salesTotal > 0 && (float) ($receivables['total'] ?? 0) > ($salesTotal * 0.6)) {
            $items[] = [
                'tone' => 'warning',
                'title' => 'Recouvrement a accelerer',
                'message' => 'Les creances ouvertes representent plus de 60 % du CA de la periode.',
                'action_url' => route('collections.index'),
                'action_label' => 'Ouvrir le recouvrement',
            ];
        }

        if ((float) ($payables['total'] ?? 0) > 0 && (float) ($receivables['total'] ?? 0) > 0 && (float) ($payables['total'] ?? 0) > ((float) ($receivables['total'] ?? 0) * 0.8)) {
            $items[] = [
                'tone' => 'info',
                'title' => 'Tension fournisseurs a surveiller',
                'message' => 'Les dettes fournisseurs approchent le niveau des creances ouvertes. Le pilotage cash doit rester serre.',
                'action_url' => route('purchases.index', ['payment_status' => 'unpaid']),
                'action_label' => 'Voir les achats ouverts',
            ];
        }

        if ((int) ($stock['alerts'] ?? 0) > 0) {
            $items[] = [
                'tone' => 'warning',
                'title' => 'Stock critique visible dans les rapports',
                'message' => number_format((float) ($stock['alerts'] ?? 0), 0, ',', ' ').' article(s) remontent deja en alerte stock.',
                'action_url' => route('stock.index', ['stock_state' => 'low']),
                'action_label' => 'Voir le stock',
            ];
        }

        $weakSupplier = collect($supplierPerformance)
            ->filter(fn (array $row) => (float) ($row['spend_total'] ?? 0) > 0)
            ->sortBy('score')
            ->first();

        if ($weakSupplier && (float) ($weakSupplier['score'] ?? 0) < 60) {
            $items[] = [
                'tone' => 'warning',
                'title' => 'Fournisseur a sous-performance',
                'message' => ($weakSupplier['supplier_name'] ?? 'Un fournisseur').' tombe a '.number_format((float) ($weakSupplier['score'] ?? 0), 1, ',', ' ').' / 100.',
                'action_url' => route('suppliers.show', $weakSupplier['supplier_id']),
                'action_label' => 'Voir le fournisseur',
            ];
        }

        if (($appMonitoring['status'] ?? 'ok') !== 'ok') {
            $items[] = [
                'tone' => ($appMonitoring['status'] ?? 'warning') === 'fail' ? 'danger' : 'warning',
                'title' => 'Contexte technique a garder en vue',
                'message' => 'Le monitoring applicatif remonte encore des incidents qui peuvent perturber l execution terrain.',
                'action_url' => route('ops.index'),
                'action_label' => 'Ouvrir Ops',
            ];
        }

        $items = $this->rank($items);

        if ($items === []) {
            $items[] = [
                'tone' => 'success',
                'title' => 'Lecture dirigeant plutot sereine',
                'message' => 'La periode ne remonte pas de tension business immediate. Le focus peut rester sur la croissance et la qualite.',
                'action_url' => route('reports.index', $filters),
                'action_label' => 'Relire le rapport',
            ];
        }

        return [
            'headline' => 'Briefing automatique dirigeant',
            'summary' => collect($items)->pluck('title')->take(3)->implode(' | '),
            'items' => array_slice($items, 0, 4),
        ];
    }

    private function dashboardHeadline(string $profileKey, array $items): string
    {
        $dangerCount = collect($items)->where('tone', 'danger')->count();

        if ($dangerCount > 0) {
            return $dangerCount.' tension(s) prioritaire(s) a traiter sur le pilotage du jour';
        }

        return match ($profileKey) {
            'direction' => 'Lecture dirigeant du jour en langage simple',
            'operations' => 'Lecture exploitation priorisee pour agir vite',
            'cashier' => 'Lecture caisse priorisee pour rester fluide',
            default => 'Lecture premium du cockpit entreprise',
        };
    }

    private function rank(array $items): array
    {
        $rank = ['danger' => 0, 'warning' => 1, 'info' => 2, 'success' => 3];

        return collect($items)
            ->sortBy(fn (array $item): int => $rank[$item['tone']] ?? 99)
            ->values()
            ->all();
    }

    private function money(float $value): string
    {
        return number_format($value, 0, ',', ' ').' XOF';
    }
}
