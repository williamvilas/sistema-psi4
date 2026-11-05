<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TwoFactorResetMail extends Mailable
{
    public function __construct(
        public string $name,
        public string $url,
        public int $expiresInMinutes,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('two-factor.mail_subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'mails.two-factor-reset');
    }
}
