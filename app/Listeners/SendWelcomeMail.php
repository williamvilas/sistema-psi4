<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\RegisterLog;
use App\Mail\WelcomeMail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;

class SendWelcomeMail
{
    /**
     * Falha no envio nunca deve impedir o cadastro.
     */
    public function handle(Registered $event): void
    {
        try {
            Mail::to($event->user->email)->send(new WelcomeMail((string) $event->user->name));
        } catch (\Throwable $t) {
            RegisterLog::dispatchSync([
                'message' => 'Falha ao enviar e-mail de boas-vindas: '.$t->getMessage(),
                'route' => request()->fullUrl(),
                'method' => request()->method(),
            ]);
        }
    }
}
