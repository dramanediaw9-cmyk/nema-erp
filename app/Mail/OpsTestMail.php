<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OpsTestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $companyName,
        public readonly string $recipient,
        public readonly string $subjectLine,
        public readonly ?string $sentBy = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ops-test',
        );
    }
}
