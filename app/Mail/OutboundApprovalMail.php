<?php

namespace App\Mail;

use App\Modules\Core\Notifications\Models\OutboundNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OutboundApprovalMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly OutboundNotification $notification)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notification->subject ?: 'Notification Nema ERP'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.outbound-approval',
            with: [
                'notification' => $this->notification,
                'companyName' => $this->notification->company?->name ?: config('app.name'),
                'actionUrl' => data_get($this->notification->meta, 'action_url'),
                'documentNumber' => data_get($this->notification->meta, 'document_number'),
                'stepLabel' => data_get($this->notification->meta, 'step_label'),
                'moduleLabel' => str((string) data_get($this->notification->meta, 'module'))->replace('_', ' ')->title()->toString(),
            ],
        );
    }
}
