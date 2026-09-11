<?php

namespace App\Mail;

use App\Models\InventoryOrder;
use App\Models\InventoryOrderApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InventoryOrderApprovalRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public InventoryOrder $order,
        public InventoryOrderApproval $approval,
    ) {}

    public function envelope(): Envelope
    {
        $label = $this->order->isInternal() ? 'Internal order' : 'RFQ';

        return new Envelope(
            subject: $label.' '.$this->order->order_number.' awaiting your approval',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.inventory.order-approval-requested',
            with: [
                'order' => $this->order,
                'approval' => $this->approval,
            ],
        );
    }
}
