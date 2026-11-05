<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SessionTranscriptionStatusEnum;
use App\Jobs\RegisterLog;
use App\Mail\SessionTranscriptionReminderMail;
use App\Models\SessionTranscriptions;
use App\Models\User;
use App\Services\SessionTranscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ExpireSessionTranscriptionsCommand extends Command
{
    protected $signature = 'app:expire-session-transcriptions-command';
    protected $description = 'Fecha transcrições abandonadas, desiste das que travaram no processamento, avisa sobre o prazo de revisão e apaga as vencidas.';

    public function handle(SessionTranscriptionService $service): int
    {
        try {
            $this->closeAbandoned($service);
            $this->failStuck($service);
            $this->remind();
            $this->expire($service);

            return self::SUCCESS;
        } catch (\Throwable $t) {
            RegisterLog::dispatchSync([
                'message' => 'Falha na varredura de transcrições. Mensagem: '.$t->getMessage(),
                'method' => 'ExpireSessionTranscriptionsCommand->handle',
            ]);

            $this->error($t->getMessage());

            return self::FAILURE;
        }
    }

    private function closeAbandoned(SessionTranscriptionService $service): void
    {
        SessionTranscriptions::query()
            ->whereIn('status', [SessionTranscriptionStatusEnum::AWAITING_CONSENT, SessionTranscriptionStatusEnum::RECORDING])
            ->where(function ($query) {
                // Sala apagada, ou encerrada há mais que a folga.
                $query->whereDoesntHave('videoRoom')
                    ->orWhereHas('videoRoom', fn ($rooms) => $rooms->where('ends_at', '<', now()->subHours((int) config('session_transcription.close_after_hours'))));
            })
            ->with('videoRoom')
            ->chunkById(100, function ($transcriptions) use ($service) {
                foreach ($transcriptions as $transcription) {
                    $service->closeAbandoned($transcription);
                }
            });
    }

    /**
     * Processamento que passou do prazo do job, por exemplo se a fila perdeu o job: o áudio ainda seria apagado pelo
     * ciclo de vida do bucket, mas a psicóloga ficaria sem resposta.
     */
    private function failStuck(SessionTranscriptionService $service): void
    {
        SessionTranscriptions::query()
            ->where('status', SessionTranscriptionStatusEnum::PROCESSING)
            ->where('ended_at', '<', now()->subHours((int) config('session_transcription.processing_hours') + 1))
            ->chunkById(100, function ($transcriptions) use ($service) {
                foreach ($transcriptions as $transcription) {
                    $service->failProcessing($transcription->uuid);
                }
            });
    }

    /**
     * O aviso roda uma vez por hora, então só pega quem entrou na janela daquele dia nesta hora. O e-mail não traz nome de
     * paciente nem nada clínico.
     */
    private function remind(): void
    {
        foreach ((array) config('session_transcription.review_warning_days') as $days) {
            $userIds = SessionTranscriptions::query()
                ->where('status', SessionTranscriptionStatusEnum::PENDING_REVIEW)
                ->whereBetween('review_deadline', [now()->addDays((int) $days)->subHour(), now()->addDays((int) $days)])
                ->distinct()
                ->pluck('user_id');

            foreach (User::query()->whereIn('id', $userIds)->get(['id', 'name', 'email']) as $user) {
                $this->sendReminder($user, (int) $days);
            }
        }
    }

    private function sendReminder(User $user, int $days): void
    {
        try {
            Mail::to($user->email)->send(new SessionTranscriptionReminderMail($user->name, $days, route('session-transcriptions.index')));
        } catch (\Throwable $t) {
            RegisterLog::dispatchSync([
                'message' => $t->getMessage(),
                'method' => 'ExpireSessionTranscriptionsCommand->sendReminder',
            ]);
        }
    }

    private function expire(SessionTranscriptionService $service): void
    {
        SessionTranscriptions::query()
            ->where('status', SessionTranscriptionStatusEnum::PENDING_REVIEW)
            ->where('review_deadline', '<', now())
            ->chunkById(100, function ($transcriptions) use ($service) {
                foreach ($transcriptions as $transcription) {
                    $service->expire($transcription);
                }
            });
    }
}
