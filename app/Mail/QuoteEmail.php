<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;
use App\Models\Sales\Quote;

class QuoteEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Quote $quote,
        public string $emailSubject,
        public string $emailBody,
        public string $pdfData
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->quote->issuing_company->email,
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->emailBody,
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfData, "Cotizacion-{$this->quote->quote_number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}