<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AssignmentReportEmail extends Mailable
{
    public function __construct(
        public string $companyName,
        public string $customerName,
        public string $address,
        public string $performedOn,
        public string $reportUrl,
        public ?string $customerMessage,
        public string $senderEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Din fotorapport är klar',
            replyTo: [new Address($this->senderEmail)],
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.assignment-report-html',
            text: 'emails.assignment-report-text',
            with: [
                'companyName' => $this->companyName,
                'customerName' => $this->customerName,
                'address' => $this->address,
                'performedOn' => $this->performedOn,
                'reportUrl' => $this->reportUrl,
                'customerMessage' => $this->customerMessage,
            ],
        );
    }
}
