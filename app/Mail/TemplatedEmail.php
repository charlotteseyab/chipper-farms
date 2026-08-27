<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TemplatedEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $emailSubject,
        public string $body,
        public ?string $actionUrl,
        public ?string $actionLabel,
        public string $storeName,
        public ?string $supportEmail = null,
        public ?string $supportPhone = null,
        public ?string $secondaryActionUrl = null,
        public ?string $secondaryActionLabel = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.template');
    }
}
