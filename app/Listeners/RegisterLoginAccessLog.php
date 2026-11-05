<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AccessLogActionEnum;
use App\Jobs\RegisterLog;
use App\Services\AccessLogService;
use Illuminate\Auth\Events\Login;

class RegisterLoginAccessLog
{
    public function __construct(private readonly AccessLogService $accessLogService)
    {
    }

    // Falha na auditoria do login não pode impedir a entrada na conta: só gera alerta.
    public function handle(Login $event): void
    {
        try {
            $this->accessLogService->register(userId: (int) $event->user->getAuthIdentifier(), action: AccessLogActionEnum::LOGIN);
        } catch (\Throwable $t) {
            RegisterLog::dispatchSync([
                'message' => 'Falha ao registrar login na trilha de acesso. Mensagem: '.$t->getMessage(),
                'method' => 'RegisterLoginAccessLog->handle',
            ]);
        }
    }
}
