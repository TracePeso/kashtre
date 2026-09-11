<?php

namespace App\Mail;

use App\Models\InventoryOrder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InventoryOrderApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public InventoryOrder $order,
        public User $approver,
    ) {}

    public function envelope(): Envelope
    {
        $label = $this->order->isInternal() ? 'Internal order' : 'RFQ';

        return new Envelope(
            subject: $label.' '.$this->order->order_number.' approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.inventory.order-approved',
            with: [
                'order' => $this->order,
                'approver' => $this->approver,
            ],
        );
    }
}
