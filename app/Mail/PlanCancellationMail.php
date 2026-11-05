<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PlanCancellationMail extends Mailable implements ShouldQueue
{
    public function __construct(
        public string $name,
        public string $expirationDate,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('plans.cancellation_mail_subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'mails.plan-cancellation');
    }
}
