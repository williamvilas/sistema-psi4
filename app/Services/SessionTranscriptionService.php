<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccessLogActionEnum;
use App\Enums\FeatureUsageEnum;
use App\Enums\PlanUserEnum;
use App\Enums\SessionTranscriptionConsentEnum;
use App\Enums\SessionTranscriptionStatusEnum;
use App\Exceptions\PlanLimitException;
use App\Exceptions\SessionTranscriptionConflictException;
use App\Helpers\FileHelper;
use App\Helpers\PublicKeyEnvelope;
use App\Helpers\SessionTranscriptHelper;
use App\Jobs\ProcessSessionTranscriptionJob;
use App\Jobs\RegisterLog;
use App\Models\Agenda;
use App\Models\Patients;
use App\Models\SessionTranscriptionConsents;
use App\Models\SessionTranscriptions;
use App\Models\User;
use App\Models\UserFeatureUsages;
use App\Models\VideoRoom;
use App\Wrappers\AwsTranscribeWrapper;
use App\Wrappers\SessionNoteWrapper;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Transcrição da sessão de vídeo e rascunho do registro. O navegador da psicóloga grava o áudio em trechos e envia direto
 * ao S3 temporário. Depois da sessão, a fila transcreve, gera o rascunho com IA, cifra com a chave pública dela e apaga o
 * áudio e o texto. O que fica guardado só ela abre, com a senha mestre.
 *
 * Nada é gravado sem o aceite do paciente, e nada vira prontuário sem a decisão da psicóloga. Enquanto rascunho
 * (`PENDING_REVIEW`), não é prontuário: fora do limite, da exportação e da guarda. Começar não pede autorização no PSI4:
 * quem tiver só o JWT não lê nada, não grava sem o aceite e não escreve no prontuário. Uma recusa ou revogação encerra
 * aquele pedido; a psicóloga pode abrir outro na mesma sala, com novo uuid e novo consentimento.
 */
readonly class SessionTranscriptionService
{
    private const int LINK_MINUTES = 10;

    private const int ADULT_AGE = 18;

    private const string NOTE_MAGIC = 'PSN';

    private const int NOTE_VERSION = 1;

    private const string NOTE_INFO = 'psi4-session-note';

    public function __construct(
        private SessionTranscriptions $transcriptions,
        private SessionTranscriptionConsents $consents,
        private Patients $patients,
        private User $users,
        private UserFeatureUsages $featureUsages,
        private SessionActivityService $sessionActivityService,
        private EncryptionKeyService $encryptionKeyService,
        private MedicalRecordService $medicalRecordService,
        private AccessLogService $accessLogService,
        private FileService $fileService,
        private AwsTranscribeWrapper $awsTranscribeWrapper,
        private SessionNoteWrapper $sessionNoteWrapper,
    ) {
    }

    public static function isEnabled(): bool
    {
        return (bool) config('session_transcription.enabled');
    }

    /**
     * O que o Jitsi precisa para oferecer a transcrição. Não devolve dado do paciente.
     */
    public function context(VideoRoom $videoRoom): array
    {
        $agenda = $this->sessionActivityService->agendaOf($videoRoom);
        $publicKey = $agenda ? ($this->encryptionKeyService->details($agenda->user_id)['scale_public_key'] ?? null) : null;
        $birthDate = $agenda ? $this->patients->newQuery()->whereKey($agenda->patient_id)->value('birth_date') : null;
        $reason = $this->cannotStartReason($agenda, $publicKey, $birthDate);
        $active = $reason === null ? $this->transcriptions->activeInRoom($videoRoom->id) : null;

        return [
            'can_start' => $reason === null,
            'reason' => $reason,
            'requires_adult_confirmation' => $reason === null && $birthDate === null,
            'minutes_left' => $reason === null ? $this->usage($agenda->user_id)['left'] : null,
            'segment_seconds' => (int) config('session_transcription.segment_seconds'),
            // Para a psicóloga continuar depois de recarregar a página, sem sobrescrever os trechos já enviados.
            'active' => $active ? [
                'uuid' => $active->uuid,
                'status' => $active->status->value,
                'segments_count' => $active->parts_count,
                'elapsed_ms' => $active->started_at ? (int) $active->started_at->diffInMilliseconds(now()) : 0,
            ] : null,
        ];
    }

    /**
     * Abre o pedido de consentimento. Reenviar o mesmo uuid não duplica.
     *
     * @param  array{uuid: string, adult_confirmed?: bool}  $data
     */
    public function request(VideoRoom $videoRoom, array $data): SessionTranscriptions
    {
        $agenda = $this->sessionActivityService->agendaOrFail($videoRoom);
        $user = $this->users->newQuery()->findOrFail($agenda->user_id);
        $active = $this->transcriptions->activeInRoom($videoRoom->id);

        if ($active) {
            return $active->uuid === $data['uuid'] ? $active : throw new SessionTranscriptionConflictException(__('session-transcriptions.already_active'));
        }

        $publicKey = $this->encryptionKeyService->details($agenda->user_id)['scale_public_key'] ?? null;
        $birthDate = $this->patients->newQuery()->whereKey($agenda->patient_id)->value('birth_date');
        $reason = $this->cannotStartReason($agenda, $publicKey, $birthDate);

        if ($reason !== null) {
            throw $reason === 'quota'
                ? new PlanLimitException(__('session-transcriptions.quota_reached'))
                : new \DomainException(__('session-transcriptions.'.$reason));
        }

        $adultConfirmed = (bool) ($data['adult_confirmed'] ?? false);

        if ($birthDate === null && ! $adultConfirmed) {
            throw new \DomainException(__('session-transcriptions.adult_confirmation_required'));
        }

        return $this->transcriptions->create([
            'uuid' => $data['uuid'],
            'user_id' => $user->id,
            'patient_id' => $agenda->patient_id,
            'agenda_id' => $agenda->id,
            'video_room_id' => $videoRoom->id,
            'status' => SessionTranscriptionStatusEnum::AWAITING_CONSENT,
            'occurred_on' => $agenda->scheduled_on->copy()->setTimezone($user->timezone ?: config('app.timezone'))->toDateString(),
            'adult_confirmed_at' => $birthDate === null ? now() : null,
        ]);
    }

    /**
     * O texto que o paciente vê. A versão e o hash vão junto para a prova do aceite.
     *
     * Quem recarregou a página e já decidiu não vê a pergunta de novo.
     *
     * @return array{text: string, version: int, decision: ?string}
     */
    public function consentText(VideoRoom $videoRoom, string $participantRef, string $uuid): array
    {
        $transcription = $this->transcriptionInRoom($videoRoom, $uuid);

        return [
            'text' => (string) config('session_transcription.consent_text'),
            'version' => (int) config('session_transcription.consent_version'),
            'decision' => $this->consents->newQuery()
                ->where('session_transcription_id', $transcription->id)
                ->where('participant_ref', $participantRef)
                ->first()?->decision->value,
        ];
    }

    /**
     * Grava a decisão de quem está na sala. Só o próprio convidado decide, pelo JWT dele: uma mensagem na sala não prova
     * consentimento. O primeiro aceite abre a gravação. A revogação para a gravação; o que foi gravado antes, com aceite,
     * segue para o rascunho, como o texto do aceite avisa. Uma recusa não reabre o mesmo pedido: a psicóloga abre outro.
     */
    public function decide(VideoRoom $videoRoom, string $participantRef, string $uuid, SessionTranscriptionConsentEnum $decision, ?string $ip): SessionTranscriptions
    {
        $transcription = $this->transcriptionInRoom($videoRoom, $uuid);
        $current = $this->consents->newQuery()
            ->where('session_transcription_id', $transcription->id)
            ->where('participant_ref', $participantRef)
            ->first();

        if ($decision === SessionTranscriptionConsentEnum::ACCEPTED && $current && $current->decision !== SessionTranscriptionConsentEnum::ACCEPTED) {
            throw new SessionTranscriptionConflictException(__('session-transcriptions.already_denied'));
        }

        if (! in_array($transcription->status, [SessionTranscriptionStatusEnum::AWAITING_CONSENT, SessionTranscriptionStatusEnum::RECORDING], true)) {
            throw new SessionTranscriptionConflictException(__('session-transcriptions.stopped'));
        }

        DB::transaction(function () use ($transcription, $participantRef, $decision, $ip) {
            $text = (string) config('session_transcription.consent_text');

            $this->consents->newQuery()->updateOrCreate(
                ['session_transcription_id' => $transcription->id, 'participant_ref' => $participantRef],
                [
                    'decision' => $decision,
                    'consent_version' => (int) config('session_transcription.consent_version'),
                    'consent_hash' => hash('sha256', $text),
                    'consent_text' => $text,
                    'decided_at' => now(),
                    'ip' => $ip,
                ],
            );

            if ($decision === SessionTranscriptionConsentEnum::ACCEPTED && $transcription->status === SessionTranscriptionStatusEnum::AWAITING_CONSENT) {
                $transcription->update(['status' => SessionTranscriptionStatusEnum::RECORDING, 'started_at' => now()]);
                $this->accessLogService->register(userId: $transcription->user_id, action: AccessLogActionEnum::SESSION_TRANSCRIPTION_STARTED);
            } elseif ($decision === SessionTranscriptionConsentEnum::REVOKED) {
                $this->accessLogService->register(userId: $transcription->user_id, action: AccessLogActionEnum::SESSION_TRANSCRIPTION_CONSENT_REVOKED);
            } elseif ($decision === SessionTranscriptionConsentEnum::REFUSED && $transcription->status === SessionTranscriptionStatusEnum::AWAITING_CONSENT) {
                $transcription->update(['status' => SessionTranscriptionStatusEnum::REFUSED, 'ended_at' => now()]);
            }
        });

        if ($decision === SessionTranscriptionConsentEnum::REVOKED) {
            return $transcription->refresh()->status === SessionTranscriptionStatusEnum::RECORDING
                ? $this->close($transcription)
                : $this->discard($transcription);
        }

        return $transcription->refresh();
    }

    /**
     * Assina o envio de um trecho direto ao S3. Cada trecho pede a sua URL, então uma revogação impede o próximo, e um
     * convidado novo sem aceite também. A contagem vem da moderadora: quem adultera o próprio cliente já ouve a chamada de
     * qualquer forma.
     *
     * @param  array{sequence: int, mime: string, bytes: int, start_ms: int, duration_ms: int, guest_count: int}  $data
     * @return array{url: string, headers: array<string, string>}
     */
    public function segmentUploadUrl(VideoRoom $videoRoom, string $uuid, array $data): array
    {
        $transcription = $this->transcriptionInRoom($videoRoom, $uuid);

        if ($transcription->status !== SessionTranscriptionStatusEnum::RECORDING) {
            throw new SessionTranscriptionConflictException(__('session-transcriptions.stopped'));
        }

        $accepted = $this->consents->newQuery()
            ->where('session_transcription_id', $transcription->id)
            ->where('decision', SessionTranscriptionConsentEnum::ACCEPTED)
            ->count();

        if ($accepted === 0 || $accepted < $data['guest_count']) {
            throw new SessionTranscriptionConflictException(__('session-transcriptions.consent_missing'));
        }

        if ($this->minutesLeft($transcription->user_id) <= 0) {
            throw new PlanLimitException(__('session-transcriptions.quota_reached'));
        }

        $upload = $this->awsTranscribeWrapper->segmentUploadUrl(
            uuid: $uuid,
            sequence: $data['sequence'],
            startMs: $data['start_ms'],
            durationMs: $data['duration_ms'],
            mime: $data['mime'],
            extension: (string) config('session_transcription.segment_mimes.'.$data['mime']),
        );

        $this->transcriptions->newQuery()
            ->whereKey($transcription->id)
            ->where('parts_count', '<=', $data['sequence'])
            ->update(['parts_count' => $data['sequence'] + 1]);

        return $upload;
    }

    public function complete(VideoRoom $videoRoom, string $uuid): SessionTranscriptions
    {
        $transcription = $this->transcriptionInRoom($videoRoom, $uuid);

        return match ($transcription->status) {
            SessionTranscriptionStatusEnum::AWAITING_CONSENT => $this->discard($transcription),
            SessionTranscriptionStatusEnum::RECORDING => $this->close($transcription),
            SessionTranscriptionStatusEnum::PROCESSING, SessionTranscriptionStatusEnum::PENDING_REVIEW => $transcription,
            default => throw new SessionTranscriptionConflictException(__('session-transcriptions.stopped')),
        };
    }

    /**
     * Uma rodada do processamento. Devolve false enquanto o Transcribe ainda trabalha, e o job volta depois.
     *
     * O texto da sessão só existe aqui, em memória: sai cifrado com a chave pública dela, e o temporário é apagado.
     */
    public function process(SessionTranscriptions $transcription): bool
    {
        $uuid = $transcription->uuid;
        $segments = $this->awsTranscribeWrapper->listSegments($uuid);

        if ($segments === []) {
            $this->finishWithoutDraft($transcription);

            return true;
        }

        foreach ($segments as $segment) {
            $this->awsTranscribeWrapper->startJob($uuid, $segment);
        }

        $done = [];

        foreach ($segments as $segment) {
            $status = $this->awsTranscribeWrapper->jobStatus($uuid, $segment['sequence']);

            if ($status === 'QUEUED' || $status === 'IN_PROGRESS') {
                return false;
            }

            // Um trecho que falhou vira lacuna: não impede o rascunho do restante.
            if ($status === 'COMPLETED') {
                $done[] = $segment;
            }
        }

        $lines = [];

        foreach ($done as $segment) {
            $output = $this->awsTranscribeWrapper->readOutput($uuid, $segment['sequence']);
            array_push($lines, ...($output ? SessionTranscriptHelper::segments($output, $segment['start_ms']) : []));
        }

        usort($lines, fn (array $a, array $b) => $a['startMs'] <=> $b['startMs']);

        if ($lines === []) {
            $this->finishWithoutDraft($transcription);

            return true;
        }

        $gaps = SessionTranscriptHelper::gaps($done);
        $publicKey = $this->encryptionKeyService->details($transcription->user_id)['scale_public_key'] ?? null;

        if (! $publicKey) {
            throw new \DomainException(__('session-transcriptions.not_activated'));
        }

        $draft = $this->sessionNoteWrapper->draft(SessionTranscriptHelper::format($lines, $gaps));
        $note = json_encode([
            'uuid' => $uuid,
            'draft' => $draft ?? array_fill_keys(SessionNoteWrapper::FIELDS, ''),
            'transcript' => $lines,
            'gaps' => $gaps,
            'generated_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $sealed = PublicKeyEnvelope::seal($note, $publicKey, self::NOTE_MAGIC, self::NOTE_VERSION, self::NOTE_INFO, self::NOTE_INFO.'|'.$uuid);

        if (! $this->fileService->put(self::notePath($uuid), $sealed)) {
            throw new \DomainException(__('session-transcriptions.cannot_save'));
        }

        $transcription->update([
            'status' => SessionTranscriptionStatusEnum::PENDING_REVIEW,
            'review_deadline' => now()->addDays((int) config('session_transcription.review_days')),
        ]);

        $this->removeTemporary($transcription);

        return true;
    }

    /**
     * O processamento desistiu: apaga tudo, sem rascunho. Ela vê o motivo na lista de transcrições, sem conteúdo.
     */
    public function failProcessing(string $uuid): void
    {
        $transcription = $this->transcriptions->findByUuid($uuid);

        if (! $transcription || $transcription->status !== SessionTranscriptionStatusEnum::PROCESSING) {
            return;
        }

        $transcription->update(['status' => SessionTranscriptionStatusEnum::FAILED]);
        $this->removeTemporary($transcription);
        $this->removeDraft($uuid);
    }

    public function paginatePending(int $userId): LengthAwarePaginator
    {
        return $this->transcriptions->paginatePending($userId, (int) config('app.paginate'));
    }

    public function countPending(int $userId): int
    {
        return self::isEnabled() ? $this->transcriptions->countPending($userId) : 0;
    }

    public function countPendingByPatient(int $patientId, int $userId): int
    {
        return self::isEnabled() ? $this->transcriptions->countPendingByPatient($patientId, $userId) : 0;
    }

    public function paginateHistory(int $userId): LengthAwarePaginator
    {
        return $this->transcriptions->paginateHistory($userId, (int) config('app.paginate'));
    }

    public function consentProof(string $uuid, int $userId): SessionTranscriptions
    {
        return $this->transcriptions->findWithConsents($uuid, $userId)
            ?? throw new \DomainException(__('session-transcriptions.not_found'));
    }

    /**
     * Saldo de minutos do mês. Limite negativo é ilimitado, e aí `left` é null.
     *
     * @return array{used: int, limit: int, left: ?int}
     */
    public function usage(int $userId): array
    {
        $plan = $this->users->findCurrentPlanById($userId) ?: PlanUserEnum::FREE->value;
        $limit = (int) config('plans.plans.'.$plan.'.total_transcription_minutes', 0);
        $used = (int) ($this->featureUsages->countsByMonth($userId, now()->year, now()->month)[FeatureUsageEnum::TRANSCRIPTION_MINUTES->value] ?? 0);

        return ['used' => $used, 'limit' => $limit, 'left' => $limit < 0 ? null : max(0, $limit - $used)];
    }

    /**
     * O rascunho para abrir no editor. Ainda sem conteúdo: o acesso só é registrado quando ela desbloquear e pedir o link.
     */
    public function draft(string $uuid, int $userId): SessionTranscriptions
    {
        return $this->pendingOrFail($uuid, $userId);
    }

    /**
     * Link do rascunho cifrado para o navegador abrir com a senha mestre. Pedido depois do desbloqueio, para o tempo
     * diante da senha não vencer o link.
     */
    public function noteLink(string $uuid, int $userId): string
    {
        $this->pendingOrFail($uuid, $userId);

        $this->accessLogService->register(userId: $userId, action: AccessLogActionEnum::SESSION_TRANSCRIPTION_VIEWED);

        return FileHelper::getLinkTemp(path: self::notePath($uuid), minutes: self::LINK_MINUTES);
    }

    /**
     * Aprovar: o navegador já cifrou como prontuário comum o texto do editor, e só ele entra. A transcrição e o rascunho
     * da IA são apagados. Só agora conta no limite e na guarda. O bloqueio impede que dois envios simultâneos criem dois
     * registros. Não preenche agenda_id: apagar a consulta apagaria o prontuário (DeleteAgendaCommand).
     */
    public function accept(string $uuid, int $userId, string $notesPath): SessionTranscriptions
    {
        $transcription = DB::transaction(function () use ($uuid, $userId, $notesPath) {
            // Pode ter vencido com o editor aberto: o texto na tela continua lá para ela copiar.
            $transcription = $this->transcriptions->findPendingForUser($uuid, $userId, lock: true)
                ?? throw new \DomainException(__('session-transcriptions.draft_unavailable'));

            $record = $this->medicalRecordService->store([
                'patient_id' => $transcription->patient_id,
                'title' => (string) config('session_transcription.record_title'),
                'notes_path' => $notesPath,
                'date_of_consultation' => $transcription->occurred_on->toDateString(),
            ], $userId);

            $transcription->update([
                'status' => SessionTranscriptionStatusEnum::ACCEPTED,
                'medical_record_id' => $record->id,
                'reviewed_at' => now(),
            ]);

            $this->accessLogService->register(userId: $userId, action: AccessLogActionEnum::SESSION_TRANSCRIPTION_ACCEPTED, medicalRecordId: $record->id);

            return $transcription;
        });

        // Arquivo removido só após o commit, para não perder o texto se a transação falhar.
        $this->removeDraft($uuid);

        return $transcription->refresh();
    }

    /**
     * Recusar apaga de imediato, sem lixeira e sem precisar da senha mestre, porque nada é lido. Fica só o que prova o
     * consentimento, sem conteúdo.
     */
    public function reject(string $uuid, int $userId): void
    {
        DB::transaction(function () use ($uuid, $userId) {
            $transcription = $this->pendingOrFail($uuid, $userId, lock: true);

            $transcription->update(['status' => SessionTranscriptionStatusEnum::REJECTED, 'reviewed_at' => now()]);
            $this->accessLogService->register(userId: $userId, action: AccessLogActionEnum::SESSION_TRANSCRIPTION_REJECTED);
        });

        $this->removeDraft($uuid);
    }

    /**
     * Fecha a transcrição que a psicóloga não encerrou, por exemplo se fechou a aba: o que já foi enviado vai para a fila.
     */
    public function closeAbandoned(SessionTranscriptions $transcription): void
    {
        if ($transcription->status === SessionTranscriptionStatusEnum::AWAITING_CONSENT) {
            $this->discard($transcription);

            return;
        }

        $this->close($transcription);
    }

    public function expire(SessionTranscriptions $transcription): void
    {
        DB::transaction(function () use ($transcription) {
            $transcription->update(['status' => SessionTranscriptionStatusEnum::EXPIRED]);
            $this->accessLogService->register(userId: $transcription->user_id, action: AccessLogActionEnum::SESSION_TRANSCRIPTION_EXPIRED);
        });

        $this->removeDraft($transcription->uuid);
    }

    /**
     * Apaga a pasta do rascunho cifrado, sem depender da contagem. A decisão já foi gravada: uma falha aqui vira alerta, não
     * erro para a psicóloga, para o texto "apagado" não ficar esquecido no storage sem ninguém saber.
     */
    public function removeDraft(string $uuid): void
    {
        $failure = null;

        try {
            $removed = FileHelper::removeDirectory(self::draftDirectory($uuid));
        } catch (\Throwable $t) {
            $removed = false;
            $failure = $t->getMessage();
        }

        if (! $removed) {
            RegisterLog::dispatchSync([
                'message' => 'Falha ao apagar o rascunho da transcrição '.$uuid.($failure ? '. Mensagem: '.$failure : ''),
                'method' => 'SessionTranscriptionService->removeDraft',
            ]);
        }
    }

    /**
     * Apaga o áudio, a saída do Transcribe e os jobs. Zera a contagem só quando deu certo: o que sobrar fica marcado, e o
     * ciclo de vida do bucket apaga em 24 h de qualquer forma.
     */
    public function removeTemporary(SessionTranscriptions $transcription): void
    {
        if ($transcription->parts_count === 0) {
            return;
        }

        try {
            $this->awsTranscribeWrapper->removeSession($transcription->uuid, $transcription->parts_count - 1);
        } catch (\Throwable) {
            // O wrapper já registrou a falha.
            return;
        }

        $transcription->update(['parts_count' => 0]);
    }

    private static function draftDirectory(string $uuid): string
    {
        return 'session-transcripts/'.$uuid;
    }

    public static function notePath(string $uuid): string
    {
        return self::draftDirectory($uuid).'/note.psn';
    }

    /**
     * Fim da gravação. Com algum trecho enviado, vai para a fila; sem nenhum, não há o que transcrever.
     */
    private function close(SessionTranscriptions $transcription): SessionTranscriptions
    {
        $segments = $transcription->refresh()->parts_count;
        $endedAt = now();
        // Sessão abandonada: conta só até o fim previsto da sala, não até a varredura.
        $limit = $transcription->videoRoom?->ends_at;
        $end = $limit && $endedAt->gt($limit) ? $limit : $endedAt;
        $seconds = $transcription->started_at ? max(0, (int) $transcription->started_at->diffInSeconds($end)) : 0;

        DB::transaction(function () use ($transcription, $segments, $endedAt, $seconds) {
            $transcription->update([
                'status' => $segments > 0 ? SessionTranscriptionStatusEnum::PROCESSING : SessionTranscriptionStatusEnum::DISCARDED,
                'ended_at' => $endedAt,
                'duration_seconds' => $seconds,
            ]);

            if ($seconds > 0) {
                $this->featureUsages->upsert($transcription->user_id, FeatureUsageEnum::TRANSCRIPTION_MINUTES, (int) ceil($seconds / 60));
            }

            if ($segments > 0) {
                ProcessSessionTranscriptionJob::dispatch($transcription->uuid);
            }
        });

        return $transcription->refresh();
    }

    private function discard(SessionTranscriptions $transcription): SessionTranscriptions
    {
        $transcription->update(['status' => SessionTranscriptionStatusEnum::DISCARDED, 'ended_at' => $transcription->ended_at ?? now()]);
        $this->removeTemporary($transcription);

        return $transcription->refresh();
    }

    /**
     * Nada audível foi gravado: não há rascunho, e o temporário sai.
     */
    private function finishWithoutDraft(SessionTranscriptions $transcription): void
    {
        $transcription->update(['status' => SessionTranscriptionStatusEnum::DISCARDED]);
        $this->removeTemporary($transcription);
    }

    private function cannotStartReason(?Agenda $agenda, ?string $publicKey, mixed $birthDate): ?string
    {
        if (! self::isEnabled()) {
            return 'unavailable';
        }

        if (! $agenda) {
            return 'no_agenda';
        }

        // A chave pública cifra o rascunho e vem da ativação das dinâmicas.
        if (! $publicKey) {
            return 'not_activated';
        }

        // Menores ficam de fora: exigem o consentimento dos responsáveis e o assentimento do menor, sem como provar aqui.
        if ($birthDate !== null && Carbon::parse($birthDate)->age < self::ADULT_AGE) {
            return 'minor';
        }

        return $this->minutesLeft($agenda->user_id) <= 0 ? 'quota' : null;
    }

    private function minutesLeft(int $userId): int
    {
        return $this->usage($userId)['left'] ?? PHP_INT_MAX;
    }

    private function transcriptionInRoom(VideoRoom $videoRoom, string $uuid): SessionTranscriptions
    {
        return $this->transcriptions->findInRoom($uuid, $videoRoom->id)
            ?? throw new \DomainException(__('session-transcriptions.not_found'));
    }

    private function pendingOrFail(string $uuid, int $userId, bool $lock = false): SessionTranscriptions
    {
        return $this->transcriptions->findPendingForUser($uuid, $userId, $lock)
            ?? throw new \DomainException(__('session-transcriptions.not_found'));
    }
}
