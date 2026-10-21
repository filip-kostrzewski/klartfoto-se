<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewAccountConfirmed extends Mailable
{
    public function __construct(
        public ?string $companyName,
        public string $name,
        public string $email,
        public string $registeredAt,
        public ?string $plan,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), (string) config('mail.from.name')),
            replyTo: [new Address($this->email)],
            subject: 'Nytt bekräftat konto på Klartfoto',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-account-confirmed',
            text: 'emails.new-account-confirmed-text',
            with: [
                'companyName' => $this->companyName,
                'name' => $this->name,
                'email' => $this->email,
                'registeredAt' => $this->registeredAt,
                'plan' => $this->plan,
            ],
        );
    }
}
