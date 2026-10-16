<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContactMessage extends Mailable
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $company,
        public string $messageBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), (string) config('mail.from.name')),
            replyTo: [new Address($this->email)],
            subject: 'New contact message for Klartfoto',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-message',
            text: 'emails.contact-message-text',
            with: [
                'name' => $this->name,
                'email' => $this->email,
                'company' => $this->company,
                'messageBody' => $this->messageBody,
            ],
        );
    }
}
