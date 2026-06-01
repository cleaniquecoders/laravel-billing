<?php

namespace CleaniqueCoders\LaravelBilling\Mail;

use CleaniqueCoders\LaravelBilling\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceIssuedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invoice '.$this->invoice->number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'billing::mail.invoice-issued',
            with: [
                'invoice' => $this->invoice,
            ],
        );
    }
}
