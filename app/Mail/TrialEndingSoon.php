<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TrialEndingSoon extends Mailable
{
    public function __construct(public Company $company) {}

    public function envelope(): Envelope
    {
        $daysLeft = $this->company->trialDaysLeft();

        return new Envelope(
            subject: $daysLeft === 1
                ? 'Din provperiod i Klartfoto slutar i morgon'
                : "Din provperiod i Klartfoto slutar om {$daysLeft} dagar",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.trial-ending-soon',
            with: [
                'company' => $this->company,
                'endsOn' => $this->company->trial_ends_at?->timezone('Europe/Stockholm')->locale('sv')->translatedFormat('j F Y'),
                'billingUrl' => route('settings.billing'),
            ],
        );
    }
}
