<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordResetMail extends Mailable implements ShouldQueue
{
    public function __construct(
        public string $name,
        public string $url,
        public int $expiresInMinutes,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('password-reset.mail_subject'));
    }

    public function content(): Content
    {
        return new Content(view: 'mails.password-reset');
    }
}
