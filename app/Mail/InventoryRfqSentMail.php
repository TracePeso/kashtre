<?php

namespace App\Mail;

use App\Models\InventoryOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InventoryRfqSentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public InventoryOrder $order,
        public string $pdfContent,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Request for Quotation '.$this->order->order_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.inventory.rfq-sent',
            with: [
                'order' => $this->order,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->order->order_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
