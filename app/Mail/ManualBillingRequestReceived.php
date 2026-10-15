<?php

namespace App\Mail;

use App\CompanyPlan;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ManualBillingRequestReceived extends Mailable
{
    /**
     * @param  array{company_name: string, organisation_number: string, invoice_email: string, billing_address: string}  $billingDetails
     */
    public function __construct(
        public int $companyId,
        public string $companyName,
        public CompanyPlan $plan,
        public array $billingDetails,
        public string $requestedAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Fakturaförfrågan för företag #{$this->companyId}",
        );
    }

    public function content(): Content
    {
        $details = $this->billingDetails;
        $body = '<h1>Ny fakturaförfrågan</h1>'
            .'<p>Företag #'.$this->companyId.': '.e($this->companyName).'</p>'
            .'<p>Begärt plan: '.e($this->plan->value).'</p>'
            .'<p>Företagsnamn för fakturan: '.e($details['company_name']).'</p>'
            .'<p>Organisationsnummer: '.e($details['organisation_number']).'</p>'
            .'<p>Faktura-e-post: '.e($details['invoice_email']).'</p>'
            .'<p>Fakturaadress:<br>'.nl2br(e($details['billing_address'])).'</p>'
            .'<p>Begärd: '.e($this->requestedAt).'</p>';

        return new Content(htmlString: $body);
    }
}
