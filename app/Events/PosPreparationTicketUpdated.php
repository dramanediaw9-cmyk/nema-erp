<?php

namespace App\Events;

use App\Modules\Pos\Models\PosPreparationTicket;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PosPreparationTicketUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $companyId,
        public readonly int $branchId,
        public readonly int $ticketId,
        public readonly ?int $displayId,
        public readonly ?string $targetArea,
        public readonly string $status,
        public readonly string $action,
        public readonly string $ticketNumber,
    ) {
    }

    public static function fromTicket(PosPreparationTicket $ticket, string $action): self
    {
        return new self(
            companyId: (int) $ticket->company_id,
            branchId: (int) $ticket->branch_id,
            ticketId: (int) $ticket->id,
            displayId: $ticket->display_id ? (int) $ticket->display_id : null,
            targetArea: $ticket->target_area,
            status: (string) $ticket->status,
            action: $action,
            ticketNumber: (string) $ticket->ticket_number,
        );
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("pos.preparation.{$this->companyId}.{$this->branchId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'pos.preparation.ticket.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'ticket_id' => $this->ticketId,
            'display_id' => $this->displayId,
            'target_area' => $this->targetArea,
            'status' => $this->status,
            'action' => $this->action,
            'ticket_number' => $this->ticketNumber,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
