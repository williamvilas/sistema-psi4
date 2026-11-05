<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyNewRegistrationLeadJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $leadId, public string $name)
    {
    }

    // Só nome e id: o WhatsApp fica no banco (app:pending-leads-command), fora do Telegram.
    public function handle(TelegramService $telegramService): void
    {
        $telegramService->sendMessage(
            "Novo pedido de convite #{$this->leadId}: {$this->name}\nVeja com: php artisan app:pending-leads-command"
        );
    }
}
