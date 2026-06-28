<?php

namespace App\Mail;

use App\Models\InventoryPurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InventoryLpoIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public InventoryPurchaseOrder $purchaseOrder,
        public string $pdfContent,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Local Purchase Order '.$this->purchaseOrder->po_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.inventory.lpo-issued',
            with: [
                'purchaseOrder' => $this->purchaseOrder,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->purchaseOrder->po_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
