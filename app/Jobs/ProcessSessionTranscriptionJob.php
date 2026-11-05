<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SessionTranscriptionStatusEnum;
use App\Models\SessionTranscriptions;
use App\Services\SessionTranscriptionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Transcreve a sessão em lote e gera o rascunho. O payload leva só o uuid: o texto existe em claro apenas na memória deste
 * job, nunca na fila, no log ou numa exceção.
 *
 * Enquanto o Transcribe trabalha, o job se devolve à fila e volta depois. Tenta até o prazo, porque o ciclo de vida do
 * bucket apaga o áudio em 24 h; exceções seguidas encerram antes.
 */
class ProcessSessionTranscriptionJob implements ShouldQueue
{
    use Queueable;

    private const int POLL_SECONDS = 30;

    // Abaixo do --timeout=90 do worker: a chamada ao modelo leva dezenas de segundos numa sessão longa.
    public int $timeout = 85;

    public int $maxExceptions = 3;

    public array $backoff = [60, 300];

    public function __construct(public string $uuid)
    {
        $this->afterCommit();
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours((int) config('session_transcription.processing_hours'));
    }

    public function handle(SessionTranscriptionService $sessionTranscriptionService): void
    {
        $transcription = SessionTranscriptions::query()->where('uuid', $this->uuid)->first();

        if (! $transcription || $transcription->status !== SessionTranscriptionStatusEnum::PROCESSING) {
            return;
        }

        if (! $sessionTranscriptionService->process($transcription)) {
            $this->release(self::POLL_SECONDS);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        app(SessionTranscriptionService::class)->failProcessing($this->uuid);
    }
}
