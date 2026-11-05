<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

class WelcomeMail extends Mailable implements ShouldQueue
{
    public ?string $firstName;
    public string $whatsappUrl;

    public function __construct(string $name)
    {
        $this->firstName = $this->resolveFirstName($name);
        $this->whatsappUrl = generateLinkWhatsApp(__('welcome.whatsapp_message'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address(config('info.support.email'), config('info.support.name'))],
            subject: __('welcome.mail_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mails.welcome', with: ['supportName' => config('info.support.name')]);
    }

    /**
     * Nomes derivados do e-mail (ex.: login social sem nome) não são usados no cumprimento.
     */
    private function resolveFirstName(string $name): ?string
    {
        $name = trim($name);

        if ($name === '' || preg_match('/[@\d._]/', $name)) {
            return null;
        }

        return Str::before($name, ' ');
    }
}
