<?php

namespace App\Mail;

use App\Models\HrBiometricEnrollmentSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HrBiometricEnrollmentCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly HrBiometricEnrollmentSession $enrollmentSession,
        public readonly string $secretCode
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'HR biometric enrollment code'
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.hr.biometric-enrollment-code',
            with: [
                'enrollmentSession' => $this->enrollmentSession,
                'secretCode' => $this->secretCode,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
