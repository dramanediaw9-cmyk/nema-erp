<?php

namespace App\Modules\Pos\Services;

use App\Modules\Pos\Models\PosComboChoice;
use App\Modules\Pos\Models\PosMenuCategory;
use App\Modules\Pos\Models\PosNoteTemplate;
use App\Modules\Pos\Models\PosPreparationDisplay;
use App\Modules\Pos\Models\PosPreparationPrinter;
use App\Modules\Pos\Models\PosPreparationTicket;
use App\Modules\Pos\Models\PosPreparationTicketItem;
use App\Modules\Pos\Models\PosProductTag;
use App\Modules\Pos\Models\PosProfile;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosPreparationService
{
    public function ensureTicketsForInvoice(SalesInvoice $invoice, ?int $userId = null): EloquentCollection
    {
        $invoice->loadMissing(['items.product', 'posSession']);

        $existing = $invoice->preparationTickets()
            ->with(['items.product', 'printer', 'display', 'profile', 'session', 'invoice'])
            ->orderBy('id')
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $context = $this->context($invoice->company_id, $invoice->branch_id);
        if (! $context['can_prepare']) {
            return new EloquentCollection();
        }

        $groups = [];
        foreach ($invoice->items as $item) {
            $routing = $this->routeItem($item, $context);
            if (! $routing['should_prepare']) {
                continue;
            }

            $groupKey = implode(':', [
                $routing['target_area'] ?? 'Preparation',
                $routing['printer']?->id ?? 'printer-none',
                $routing['display']?->id ?? 'display-none',
            ]);

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'routing' => $routing,
                    'items' => [],
                ];
            }

            $groups[$groupKey]['items'][] = $item;
        }

        if ($groups === []) {
            return new EloquentCollection();
        }

        return DB::transaction(function () use ($groups, $invoice, $context, $userId) {
            $created = [];
            $sequence = 1;

            foreach ($groups as $group) {
                $routing = $group['routing'];
                $ticket = PosPreparationTicket::query()->create([
                    'tenant_id' => $invoice->tenant_id,
                    'company_id' => $invoice->company_id,
                    'branch_id' => $invoice->branch_id,
                    'pos_session_id' => $invoice->pos_session_id,
                    'sales_invoice_id' => $invoice->id,
                    'pos_profile_id' => $context['profile']?->id,
                    'printer_id' => $routing['printer']?->id,
                    'display_id' => $routing['display']?->id,
                    'ticket_number' => $this->ticketNumber($invoice, $sequence),
                    'target_area' => $routing['target_area'],
                    'status' => 'queued',
                    'priority' => $routing['priority'],
                    'target_minutes' => $routing['target_minutes'],
                    'note_snapshot' => $context['note_template']?->content,
                    'notes' => $routing['notes'],
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                foreach ($group['items'] as $item) {
                    $itemDetails = $this->itemDetails($item, $context);

                    PosPreparationTicketItem::query()->create([
                        'preparation_ticket_id' => $ticket->id,
                        'sales_invoice_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'description' => $item->description,
                        'qty' => $item->qty,
                        'status' => 'queued',
                        'combo_label' => $itemDetails['combo_label'],
                        'menu_category_labels' => $itemDetails['menu_category_labels'],
                        'tag_labels' => $itemDetails['tag_labels'],
                    ]);
                }

                $created[] = $ticket->id;
                $sequence++;
            }

            return PosPreparationTicket::query()
                ->with(['items.product', 'printer', 'display', 'profile', 'session', 'invoice'])
                ->whereKey($created)
                ->orderBy('id')
                ->get();
        });
    }

    public function board(int $companyId, int $branchId, array $filters = []): array
    {
        $status = filled($filters['status'] ?? null) ? (string) $filters['status'] : null;
        $printerId = filled($filters['printer_id'] ?? null) ? (int) $filters['printer_id'] : null;
        $displayId = filled($filters['display_id'] ?? null) ? (int) $filters['display_id'] : null;

        $tickets = PosPreparationTicket::query()
            ->with(['items.product', 'printer', 'display', 'invoice.customer', 'session', 'profile'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($printerId, fn (Builder $query) => $query->where('printer_id', $printerId))
            ->when($displayId, fn (Builder $query) => $query->where('display_id', $displayId))
            ->latest('created_at')
            ->latest('id')
            ->limit(30)
            ->get();

        $openTickets = PosPreparationTicket::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereNotIn('status', ['served', 'cancelled'])
            ->get();

        return [
            'summary' => [
                'queued' => (int) $openTickets->where('status', 'queued')->count(),
                'in_progress' => (int) $openTickets->where('status', 'in_progress')->count(),
                'ready' => (int) $openTickets->where('status', 'ready')->count(),
                'late' => (int) $openTickets->filter(fn (PosPreparationTicket $ticket) => $this->isLate($ticket))->count(),
            ],
            'tickets' => $tickets,
            'printers' => PosPreparationPrinter::query()
                ->where('company_id', $companyId)
                ->where(function (Builder $query) use ($branchId): void {
                    $query->where('branch_id', $branchId)->orWhereNull('branch_id');
                })
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'displays' => PosPreparationDisplay::query()
                ->where('company_id', $companyId)
                ->where(function (Builder $query) use ($branchId): void {
                    $query->where('branch_id', $branchId)->orWhereNull('branch_id');
                })
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'status_options' => [
                'queued' => 'En file',
                'in_progress' => 'En preparation',
                'ready' => 'Pret',
                'served' => 'Servi',
                'cancelled' => 'Annule',
            ],
            'filters' => [
                'status' => $status,
                'printer_id' => $printerId,
                'display_id' => $displayId,
            ],
        ];
    }

    public function displayBoard(int $companyId, int $branchId, PosPreparationDisplay $display): array
    {
        $display->loadMissing('branch');

        $visibleStatuses = ['queued', 'in_progress', 'ready'];
        $statusOptions = $this->statusOptions();

        $tickets = PosPreparationTicket::query()
            ->with(['items.product', 'printer', 'display', 'invoice.customer', 'session', 'profile'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('display_id', $display->id)
            ->whereIn('status', $visibleStatuses)
            ->latest('created_at')
            ->latest('id')
            ->get();

        return [
            'display' => $display,
            'refresh_seconds' => max(5, (int) ($display->refresh_seconds ?: 20)),
            'status_options' => $statusOptions,
            'next_status_map' => [
                'queued' => 'in_progress',
                'in_progress' => 'ready',
                'ready' => 'served',
            ],
            'previous_status_map' => [
                'in_progress' => 'queued',
                'ready' => 'in_progress',
            ],
            'summary' => [
                'queued' => (int) $tickets->where('status', 'queued')->count(),
                'in_progress' => (int) $tickets->where('status', 'in_progress')->count(),
                'ready' => (int) $tickets->where('status', 'ready')->count(),
                'late' => (int) $tickets->filter(fn (PosPreparationTicket $ticket) => $this->isLate($ticket))->count(),
            ],
            'grouped_tickets' => collect($visibleStatuses)->mapWithKeys(fn (string $status): array => [
                $status => $tickets->where('status', $status)->values(),
            ]),
        ];
    }

    public function updateTicketStatus(PosPreparationTicket $ticket, string $status, ?int $userId = null): PosPreparationTicket
    {
        if (! in_array($status, ['queued', 'in_progress', 'ready', 'served', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Statut de preparation invalide.',
            ]);
        }

        $updates = [
            'status' => $status,
            'updated_by' => $userId,
        ];

        if ($status === 'in_progress' && ! $ticket->started_at) {
            $updates['started_at'] = now();
        }

        if ($status === 'in_progress') {
            $updates['ready_at'] = null;
            $updates['served_at'] = null;
        }

        if ($status === 'ready') {
            $updates['started_at'] = $ticket->started_at ?: now();
            $updates['ready_at'] = now();
            $updates['served_at'] = null;
        }

        if ($status === 'served') {
            $updates['started_at'] = $ticket->started_at ?: now();
            $updates['ready_at'] = $ticket->ready_at ?: now();
            $updates['served_at'] = now();
        }

        if ($status === 'queued') {
            $updates['started_at'] = null;
            $updates['ready_at'] = null;
            $updates['served_at'] = null;
        }

        if ($status === 'cancelled') {
            $updates['served_at'] = null;
        }

        $ticket->update($updates);
        $ticket->items()->update(['status' => $status]);

        return $ticket->fresh(['items.product', 'printer', 'display', 'invoice.customer', 'session', 'profile']);
    }

    private function statusOptions(): array
    {
        return [
            'queued' => 'En file',
            'in_progress' => 'En preparation',
            'ready' => 'Pret',
            'served' => 'Servi',
            'cancelled' => 'Annule',
        ];
    }

    public function isLate(PosPreparationTicket $ticket): bool
    {
        if (($ticket->target_minutes ?? 0) <= 0 || in_array($ticket->status, ['ready', 'served', 'cancelled'], true)) {
            return false;
        }

        return $ticket->created_at?->copy()->addMinutes((int) $ticket->target_minutes)->isPast() ?? false;
    }

    private function context(int $companyId, int $branchId): array
    {
        $profile = PosProfile::query()
            ->with(['defaultPrinter', 'defaultDisplay'])
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($branchId): void {
                $query->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->where('is_active', true)
            ->orderByDesc('branch_id')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        $printers = PosPreparationPrinter::query()
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($branchId): void {
                $query->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->where('is_active', true)
            ->orderByDesc('branch_id')
            ->orderBy('name')
            ->get();

        $displays = PosPreparationDisplay::query()
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($branchId): void {
                $query->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->where('is_active', true)
            ->orderByDesc('branch_id')
            ->orderBy('name')
            ->get();

        $menuCategoryMap = [];
        foreach (PosMenuCategory::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('sort_order')->get() as $category) {
            foreach (($category->product_ids ?? []) as $productId) {
                $menuCategoryMap[(int) $productId][] = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'color' => $category->color,
                ];
            }
        }

        $tagMap = [];
        foreach (PosProductTag::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get() as $tag) {
            foreach (($tag->product_ids ?? []) as $productId) {
                $tagMap[(int) $productId][] = [
                    'name' => $tag->name,
                    'color' => $tag->color,
                ];
            }
        }

        $comboMap = [];
        foreach (PosComboChoice::query()
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($branchId): void {
                $query->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->where('is_active', true)
            ->orderByDesc('branch_id')
            ->orderBy('id')
            ->get() as $combo) {
            if ($combo->parent_product_id && ! isset($comboMap[$combo->parent_product_id])) {
                $comboMap[$combo->parent_product_id] = $combo;
            }
        }

        $noteTemplate = PosNoteTemplate::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('usage', ['prep', 'kitchen'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();

        return [
            'profile' => $profile,
            'printers' => $printers,
            'displays' => $displays,
            'menu_categories' => $menuCategoryMap,
            'tags' => $tagMap,
            'combos' => $comboMap,
            'note_template' => $noteTemplate,
            'can_prepare' => $printers->isNotEmpty() || $displays->isNotEmpty() || $profile?->default_printer_id || $profile?->default_display_id,
        ];
    }

    private function routeItem(SalesInvoiceItem $item, array $context): array
    {
        $details = $this->itemDetails($item, $context);
        $names = collect($details['menu_category_labels'])->filter()->values();

        $printer = $this->matchPrinter($names, $context['printers'], $context['profile']?->default_printer_id);
        $display = $this->matchDisplay($names, $context['displays'], $context['profile']?->default_display_id);

        $shouldPrepare = $printer || $display || $details['combo_label'] || ! empty($details['menu_category_labels']);
        if (! $shouldPrepare) {
            return ['should_prepare' => false];
        }

        $targetArea = $printer?->target_area
            ?: $display?->target_area
            ?: ($details['menu_category_labels'][0] ?? null)
            ?: $details['combo_label']
            ?: 'Preparation';

        $targetMinutes = max(
            (int) ($printer?->prep_time_target_minutes ?? 0),
            (int) ($display?->prep_time_target_minutes ?? 0),
        );

        return [
            'should_prepare' => true,
            'printer' => $printer,
            'display' => $display,
            'target_area' => $targetArea,
            'target_minutes' => $targetMinutes > 0 ? $targetMinutes : null,
            'priority' => $details['combo_label'] ? 'rush' : 'normal',
            'notes' => $details['combo_label']
                ? 'Combo a preparer: '.$details['combo_label']
                : (! empty($details['tag_labels']) ? 'Etiquettes: '.implode(', ', $details['tag_labels']) : null),
        ];
    }

    private function itemDetails(SalesInvoiceItem $item, array $context): array
    {
        $productId = (int) $item->product_id;
        $combo = $context['combos'][$productId] ?? null;

        return [
            'combo_label' => $combo?->name,
            'menu_category_labels' => collect($context['menu_categories'][$productId] ?? [])->pluck('name')->values()->all(),
            'tag_labels' => collect($context['tags'][$productId] ?? [])->pluck('name')->values()->all(),
        ];
    }

    private function matchPrinter(Collection $names, Collection $printers, ?int $defaultPrinterId): ?PosPreparationPrinter
    {
        $matched = $printers->first(function (PosPreparationPrinter $printer) use ($names): bool {
            return filled($printer->target_area)
                && $names->contains(fn (string $name) => mb_strtolower($name) === mb_strtolower((string) $printer->target_area));
        });

        if ($matched) {
            return $matched;
        }

        if ($defaultPrinterId) {
            /** @var PosPreparationPrinter|null $default */
            $default = $printers->firstWhere('id', $defaultPrinterId);
            if ($default) {
                return $default;
            }
        }

        /** @var PosPreparationPrinter|null $fallback */
        $fallback = $printers->first();

        return $fallback;
    }

    private function matchDisplay(Collection $names, Collection $displays, ?int $defaultDisplayId): ?PosPreparationDisplay
    {
        $matched = $displays->first(function (PosPreparationDisplay $display) use ($names): bool {
            return filled($display->target_area)
                && $names->contains(fn (string $name) => mb_strtolower($name) === mb_strtolower((string) $display->target_area));
        });

        if ($matched) {
            return $matched;
        }

        if ($defaultDisplayId) {
            /** @var PosPreparationDisplay|null $default */
            $default = $displays->firstWhere('id', $defaultDisplayId);
            if ($default) {
                return $default;
            }
        }

        /** @var PosPreparationDisplay|null $fallback */
        $fallback = $displays->first();

        return $fallback;
    }

    private function ticketNumber(SalesInvoice $invoice, int $sequence): string
    {
        return sprintf('PREP-%s-%02d', $invoice->invoice_number, $sequence);
    }
}
