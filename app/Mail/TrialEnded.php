<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TrialEnded extends Mailable
{
    public function __construct(public Company $company) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Er provperiod i Klartfoto har löpt ut',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.trial-ended',
            with: [
                'company' => $this->company,
                'billingUrl' => route('settings.billing'),
            ],
        );
    }
}
