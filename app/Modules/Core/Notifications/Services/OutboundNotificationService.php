<?php

namespace App\Modules\Core\Notifications\Services;

use App\Mail\OutboundApprovalMail;
use App\Models\User;
use App\Modules\Core\Approvals\Models\ApprovalStep;
use App\Modules\Core\Approvals\Services\ApprovalFlowService;
use App\Modules\Core\Approvals\Services\ApprovalSettingsService;
use App\Modules\Core\Notifications\Models\OutboundNotification;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Purchases\Models\PurchaseBill;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OutboundNotificationService
{
    public function __construct(
        private readonly ApprovalFlowService $approvalFlowService,
        private readonly ApprovalSettingsService $approvalSettingsService,
    ) {
    }

    public function dispatchApprovalRequest(Model $document, string $module, ?ApprovalStep $step): void
    {
        if (! $step) {
            return;
        }

        $channels = $this->approvalSettingsService->notificationChannelsForCompany($document->company_id);
        $recipients = $this->approvalFlowService->candidateApprovers($document->company_id, $module, $step);

        if (($channels['email']['enabled'] ?? false) === true) {
            foreach ($recipients as $recipient) {
                if (filled($recipient->email)) {
                    $this->queue($document, $module, $step, 'email', $recipient->email, $recipient);
                }
            }

            foreach ($this->parseList($channels['email']['copy_to'] ?? '') as $recipient) {
                $this->queue($document, $module, $step, 'email', $recipient);
            }
        }

        if (($channels['whatsapp']['enabled'] ?? false) === true) {
            foreach ($recipients as $recipient) {
                if (filled($recipient->phone)) {
                    $this->queue($document, $module, $step, 'whatsapp', $recipient->phone, $recipient);
                }
            }

            foreach ($this->parseList($channels['whatsapp']['copy_to'] ?? '') as $recipient) {
                $this->queue($document, $module, $step, 'whatsapp', $recipient);
            }
        }
    }

    public function indexQuery(int $companyId, ?string $channel = null, ?string $status = null): Builder
    {
        return OutboundNotification::query()
            ->with(['branch', 'user', 'company'])
            ->where('company_id', $companyId)
            ->when($channel, fn (Builder $query) => $query->where('channel', $channel))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->latest();
    }

    public function summaryForCompany(int $companyId): array
    {
        $query = OutboundNotification::query()->where('company_id', $companyId);

        return [
            'queued' => (clone $query)->where('status', 'queued')->count(),
            'sent' => (clone $query)->where('status', 'sent')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
            'oldest_queued_at' => (clone $query)->where('status', 'queued')->min('queued_at'),
            'last_sent_at' => (clone $query)->where('status', 'sent')->max('sent_at'),
            'last_failed_at' => (clone $query)->where('status', 'failed')->max('failed_at'),
        ];
    }

    public function processQueued(int $companyId, int $limit = 50): array
    {
        $ids = $this->claimDispatchableNotificationIds($companyId, $limit);

        $notifications = OutboundNotification::query()
            ->with(['company', 'branch', 'user'])
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        $summary = [
            'selected' => $notifications->count(),
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
        ];

        foreach ($notifications as $notification) {
            $summary['processed']++;

            $dispatched = $this->deliver($notification);

            if ($dispatched->status === 'sent') {
                $summary['sent']++;
            } else {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    public function requeueFailed(int $companyId, int $limit = 50): int
    {
        $ids = OutboundNotification::query()
            ->where('company_id', $companyId)
            ->where('status', 'failed')
            ->orderBy('failed_at')
            ->orderBy('id')
            ->limit(max($limit, 1))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        $updatedAt = now();

        return OutboundNotification::query()
            ->whereIn('id', $ids)
            ->update([
                'status' => 'queued',
                'queued_at' => $updatedAt,
                'sent_at' => null,
                'failed_at' => null,
                'failure_reason' => null,
                'updated_at' => $updatedAt,
            ]);
    }

    public function cancelQueuedForResource(Model $document, string $reason = 'Document annule avant traitement.'): int
    {
        $failedAt = now();

        return OutboundNotification::query()
            ->where('resource_type', $document::class)
            ->where('resource_id', $document->getKey())
            ->where('status', 'queued')
            ->update([
                'status' => 'failed',
                'failed_at' => $failedAt,
                'failure_reason' => Str::limit(trim($reason) ?: 'Document annule avant traitement.', 1000),
                'updated_at' => $failedAt,
            ]);
    }

    public function cancelQueuedForApprovalStep(Model $document, int $stepOrder, string $reason = 'Etape d approbation reaffectee.'): int
    {
        $failedAt = now();

        return OutboundNotification::query()
            ->where('resource_type', $document::class)
            ->where('resource_id', $document->getKey())
            ->where('step_order', $stepOrder)
            ->where('status', 'queued')
            ->update([
                'status' => 'failed',
                'failed_at' => $failedAt,
                'failure_reason' => Str::limit(trim($reason) ?: 'Etape d approbation reaffectee.', 1000),
                'updated_at' => $failedAt,
            ]);
    }

    private function queue(
        Model $document,
        string $module,
        ApprovalStep $step,
        string $channel,
        string $recipient,
        ?User $user = null,
    ): OutboundNotification {
        $code = implode(':', [
            'approval',
            $module,
            class_basename($document::class),
            $document->getKey(),
            $step->step_order,
            $channel,
            sha1(Str::lower(trim($recipient))),
        ]);

        return OutboundNotification::query()->updateOrCreate(
            ['code' => $code],
            [
                'company_id' => $document->company_id,
                'branch_id' => $document->branch_id ?? null,
                'user_id' => $user?->id,
                'channel' => $channel,
                'recipient' => trim($recipient),
                'subject' => $this->subject($module, $document, $step),
                'message' => $this->message($module, $document, $step),
                'status' => 'queued',
                'resource_type' => $document::class,
                'resource_id' => $document->getKey(),
                'step_order' => $step->step_order,
                'queued_at' => now(),
                'failed_at' => null,
                'failure_reason' => null,
                'sent_at' => null,
                'meta' => [
                    'module' => $module,
                    'step_label' => $step->label,
                    'document_number' => $this->documentNumber($module, $document),
                    'document_total' => (float) ($document->total ?? 0),
                    'action_url' => $this->actionUrl($module, $document),
                ],
            ]
        );
    }

    private function deliver(OutboundNotification $notification): OutboundNotification
    {
        $notification = DB::transaction(function () use ($notification) {
            $locked = OutboundNotification::query()
                ->whereKey($notification->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'queued') {
                $locked->forceFill([
                    'status' => 'processing',
                ])->save();
            }

            return $locked;
        });

        if ($notification->status !== 'processing') {
            return $notification->fresh(['company', 'branch', 'user']);
        }

        try {
            $delivery = match ($notification->channel) {
                'email' => $this->deliverEmail($notification),
                'whatsapp' => $this->deliverWhatsApp($notification),
                default => throw new RuntimeException('Canal de notification non supporte : '.$notification->channel),
            };

            return $this->markAsSent($notification, $delivery);
        } catch (Throwable $exception) {
            return $this->markAsFailed($notification, $exception->getMessage());
        }
    }

    private function claimDispatchableNotificationIds(int $companyId, int $limit): array
    {
        return DB::transaction(function () use ($companyId, $limit): array {
            $ids = OutboundNotification::query()
                ->where('company_id', $companyId)
                ->where(function ($query): void {
                    $query->where('status', 'queued')
                        ->orWhere(function ($processingQuery): void {
                            $processingQuery->where('status', 'processing')
                                ->where('updated_at', '<=', now()->subMinutes(15));
                        });
                })
                ->orderBy('queued_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->limit(max($limit, 1))
                ->pluck('id');

            if ($ids->isEmpty()) {
                return [];
            }

            OutboundNotification::query()
                ->whereIn('id', $ids)
                ->update([
                    'status' => 'processing',
                    'updated_at' => now(),
                ]);

            return $ids->map(fn ($id): int => (int) $id)->all();
        });
    }

    private function deliverEmail(OutboundNotification $notification): array
    {
        Mail::to($notification->recipient)->send(new OutboundApprovalMail($notification));

        return [
            'channel' => 'email',
            'transport' => (string) config('mail.default'),
            'delivered_at' => now()->toIso8601String(),
        ];
    }

    private function deliverWhatsApp(OutboundNotification $notification): array
    {
        $webhookUrl = trim((string) config('services.whatsapp.webhook_url'));

        if ($webhookUrl === '') {
            throw new RuntimeException('Le webhook WhatsApp n est pas configure.');
        }

        $request = Http::acceptJson()
            ->asJson()
            ->timeout(max((int) config('services.whatsapp.timeout', 10), 1));

        $token = trim((string) config('services.whatsapp.api_token'));
        if ($token !== '') {
            $request = $request->withToken($token);
        }

        $response = $request->post($webhookUrl, [
            'to' => $notification->recipient,
            'from' => config('services.whatsapp.from'),
            'subject' => $notification->subject,
            'message' => $notification->message,
            'notification_id' => $notification->id,
            'code' => $notification->code,
            'metadata' => [
                'company_id' => $notification->company_id,
                'branch_id' => $notification->branch_id,
                'resource_type' => $notification->resource_type,
                'resource_id' => $notification->resource_id,
                'step_order' => $notification->step_order,
                'document_number' => data_get($notification->meta, 'document_number'),
                'action_url' => data_get($notification->meta, 'action_url'),
            ],
        ]);

        if ($response->failed()) {
            $body = trim($response->body());
            throw new RuntimeException('WhatsApp HTTP '.$response->status().' : '.($body !== '' ? Str::limit($body, 180) : 'reponse vide'));
        }

        return [
            'channel' => 'whatsapp',
            'transport' => 'webhook',
            'status_code' => $response->status(),
            'reference' => $response->json('id') ?? $response->json('message_id') ?? $response->header('X-Message-Id'),
            'delivered_at' => now()->toIso8601String(),
        ];
    }

    private function markAsSent(OutboundNotification $notification, array $delivery): OutboundNotification
    {
        $notification->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
            'failed_at' => null,
            'failure_reason' => null,
            'meta' => $this->mergeMeta($notification, [
                'delivery' => $delivery,
                'last_attempt_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return $notification->fresh(['company', 'branch', 'user']);
    }

    private function markAsFailed(OutboundNotification $notification, string $reason): OutboundNotification
    {
        $notification->forceFill([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => Str::limit(trim($reason) ?: 'Echec de livraison inconnu.', 1000),
            'meta' => $this->mergeMeta($notification, [
                'last_attempt_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return $notification->fresh(['company', 'branch', 'user']);
    }

    private function mergeMeta(OutboundNotification $notification, array $data): array
    {
        return array_replace_recursive($notification->meta ?? [], $data);
    }

    private function parseList(string $raw): array
    {
        return collect(preg_split('/[\s,;]+/', $raw) ?: [])
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function subject(string $module, Model $document, ApprovalStep $step): string
    {
        return 'Approbation requise - '.$this->moduleLabel($module).' '.$this->documentNumber($module, $document).' - '.$step->label;
    }

    private function message(string $module, Model $document, ApprovalStep $step): string
    {
        return implode(' ', [
            'Une demande d approbation attend votre action.',
            'Document : '.$this->moduleLabel($module).' '.$this->documentNumber($module, $document).'.',
            'Montant : '.number_format((float) ($document->total ?? 0), 0, ',', ' ').' XOF.',
            'Etape : '.$step->label.'.',
            'Ouvrir : '.$this->actionUrl($module, $document),
        ]);
    }

    private function moduleLabel(string $module): string
    {
        return match ($module) {
            'sales' => 'vente',
            'purchases' => 'achat',
            default => 'depense',
        };
    }

    private function documentNumber(string $module, Model $document): string
    {
        return match ($module) {
            'sales' => $document instanceof SalesInvoice ? $document->invoice_number : '',
            'purchases' => $document instanceof PurchaseBill ? $document->bill_number : '',
            default => $document instanceof Expense ? $document->expense_number : '',
        };
    }

    private function actionUrl(string $module, Model $document): string
    {
        return match ($module) {
            'sales' => route('sales.show', $document),
            'purchases' => route('purchases.show', $document),
            default => route('expenses.show', $document),
        };
    }
}

