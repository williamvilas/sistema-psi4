<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\WhatsAppWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWhatsAppWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public array $payload)
    {
    }

    public function handle(WhatsAppWebhookService $whatsAppWebhookService): void
    {
        $whatsAppWebhookService->process($this->payload);
    }
}
