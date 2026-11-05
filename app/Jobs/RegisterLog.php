<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\LogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RegisterLog implements ShouldQueue
{
    use Queueable;

    public function __construct(public array $exception = [])
    {
    }

    public function handle(LogService $logService): void
    {
        try {
            $logService->store([
                'user_id' => Auth::id(),
                'message' => $this->exception['message'],
                'exception' => ! empty($this->exception['exception']) ? json_encode($this->exception['exception']) : null,
                'data' => ! empty($this->exception['data']) ? $this->exception['data'] : null,
                'route' => $this->exception['route'] ?? null,
                'method' => $this->exception['method'] ?? null,
            ]);

        } catch (\Throwable $t) {
            Log::error('Falha em salvar o log. Mensagem: '.$t->getMessage());
        }
    }
}
