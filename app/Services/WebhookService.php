<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WebhookStatusEnum;
use App\Jobs\ExecuteWebhookPaymentJob;
use App\Jobs\RegisterLog;
use App\Models\Webhook;

readonly class WebhookService
{
    public function __construct(private Webhook $webhook)
    {
    }

    public function store(array $payload): void
    {
        try {
            $webhook = $this->webhook->create([
                'payload' => $payload,
                'status' => WebhookStatusEnum::PENDING->value,
            ]);

            ExecuteWebhookPaymentJob::dispatch(webhook: $webhook);

        } catch (\Throwable $t) {
            RegisterLog::dispatchSync([
                'data' => $payload,
                'message' => $t->getMessage(),
                'route' => request()->fullUrl(),
                'method' => request()->method(),
            ]);
        }
    }
}
