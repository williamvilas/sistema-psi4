<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccessLogActionEnum;
use App\Enums\SessionActivityEnum;
use App\Exceptions\SessionActivityPinException;
use App\Helpers\FileHelper;
use App\Jobs\RegisterLog;
use App\Mail\SessionActivityPinLockedMail;
use App\Models\Agenda;
use App\Models\Patients;
use App\Models\SessionActivities;
use App\Models\User;
use App\Models\VideoRoom;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Dinâmicas de apoio da sessão de vídeo. O navegador da psicóloga cifra com a chave pública (ver crypto.js) e aqui
 * só chega texto cifrado: sem login, dá para gravar no prontuário, mas nunca ler. A cifra não prova quem escreveu,
 * por isso gravar e retomar exigem a autorização que ela aprova no PSI4 (SessionAuthorizationService).
 */
readonly class SessionActivityService
{
    private const int MAX_PIN_ATTEMPTS = 5;

    // Depois do fim da sessão, ainda é possível guardar e retomar por este prazo.
    private const int GRACE_HOURS = 6;

    private const int LINK_MINUTES = 10;

    private const array AGENDA_COLUMNS = ['id', 'user_id', 'patient_id', 'scheduled_on', 'room_link'];

    public function __construct(
        private SessionActivities $sessionActivities,
        private Agenda $agenda,
        private Patients $patients,
        private User $users,
        private EncryptionKeyService $encryptionKeyService,
        private MedicalRecordService $medicalRecordService,
        private AccessLogService $accessLogService,
        private JitsiRoomService $jitsiRoomService,
        private FileService $fileService,
        private PlanLimitService $planLimitService = new PlanLimitService,
    ) {
    }

    /**
     * O que o Jitsi precisa para abrir a biblioteca. Não devolve dado do paciente nem a chave do rascunho.
     */
    public function context(VideoRoom $videoRoom): array
    {
        $agenda = $this->agendaOf($videoRoom);
        $publicKey = $agenda ? ($this->encryptionKeyService->details($agenda->user_id)['scale_public_key'] ?? null) : null;
        $reason = $this->cannotSaveReason($agenda, $publicKey);

        return [
            'disclaimer' => config('session_activities.disclaimer'),
            'patient_notice' => config('session_activities.patient_notice'),
            'catalog' => $this->catalog(),
            'public_key' => $reason === null ? $publicKey : null,
            'can_save' => $reason === null,
            'reason' => $reason,
            'activation_url' => route('session-activities.index'),
            'draft' => $reason === null ? $this->draftScope($videoRoom, $agenda) : null,
        ];
    }

    /**
     * Confere o PIN das abas abertas antes da autorização no PSI4.
     *
     * @return array{grant: string, draft: array{key: string, psychologist_ref: string, patient_ref: string, expires_at: string}}
     */
    public function unlock(VideoRoom $videoRoom, string $pin): array
    {
        $agenda = $this->agendaOrFail($videoRoom);

        $this->checkPin($this->users->newQuery()->findOrFail($agenda->user_id), $pin);

        return $this->issueAuthorization($videoRoom);
    }

    /**
     * O que fica só em memória no Jitsi depois da aprovação: a autorização de gravação e a chave do rascunho.
     *
     * @return array{grant: string, draft: array{key: string, psychologist_ref: string, patient_ref: string, expires_at: string}}
     */
    public function issueAuthorization(VideoRoom $videoRoom): array
    {
        $agenda = $this->agendaOrFail($videoRoom);
        $this->assertWithinDeadline($videoRoom);

        return [
            'grant' => JWT::encode([
                'room' => $videoRoom->room,
                'uid' => $agenda->user_id,
                'exp' => $this->deadline($videoRoom)->getTimestamp(),
            ], $this->derivedSecret('grant'), 'HS256'),
            'draft' => [
                ...$this->draftScope($videoRoom, $agenda),
                'key' => base64_encode(hash_hmac('sha256', 'key|'.$videoRoom->room.'|'.$agenda->user_id.'|'.$agenda->patient_id, $this->derivedSecret('draft'), true)),
            ],
        ];
    }

    /**
     * Guarda no prontuário. O paciente vem sempre da consulta, nunca do cliente. Reenviar o mesmo uuid não duplica.
     *
     * @param  array{uuid: string, activity: string, content: string, grant: string}  $data
     * @return array{activity: SessionActivities, created: bool}
     */
    public function store(VideoRoom $videoRoom, array $data): array
    {
        $agenda = $this->agendaOrFail($videoRoom);
        $user = $this->users->newQuery()->findOrFail($agenda->user_id);
        $this->assertGrant($data['grant'], $videoRoom, $user);

        $existing = $this->sessionActivities->findByUuid($data['uuid']);

        if ($existing) {
            if ($existing->user_id !== $user->id || $existing->patient_id !== $agenda->patient_id) {
                throw new \DomainException(__('session-activities.cannot_save'));
            }

            return ['activity' => $existing, 'created' => false];
        }

        $activity = SessionActivityEnum::from($data['activity']);
        $notesPath = 'medical-records/sa-'.$data['uuid'].'.psa';
        $contentPath = 'session-activities/'.$data['uuid'].'.psa';
        $bytes = (string) base64_decode($data['content'], true);

        // Os dois caminhos guardam os mesmos bytes: o prontuário pode ser editado depois, o original não.
        if (! $this->fileService->put($notesPath, $bytes) || ! $this->fileService->put($contentPath, $bytes)) {
            throw new \DomainException(__('session-activities.cannot_save'));
        }

        try {
            $sessionActivity = DB::transaction(function () use ($agenda, $user, $activity, $data, $notesPath, $contentPath) {
                $occurredOn = $agenda->scheduled_on->copy()->setTimezone($user->timezone ?: config('app.timezone'))->toDateString();

                // Não preenche agenda_id: apagar a consulta apagaria o prontuário (DeleteAgendaCommand).
                $record = $this->medicalRecordService->store([
                    'patient_id' => $agenda->patient_id,
                    'title' => $activity->title(),
                    'notes_path' => $notesPath,
                    'date_of_consultation' => $occurredOn,
                ], $user->id);

                $sessionActivity = $this->sessionActivities->create([
                    'uuid' => $data['uuid'],
                    'user_id' => $user->id,
                    'patient_id' => $agenda->patient_id,
                    'agenda_id' => $agenda->id,
                    'medical_record_id' => $record->id,
                    'activity' => $activity,
                    'occurred_on' => $occurredOn,
                    'content_path' => $contentPath,
                ]);

                $this->accessLogService->register(userId: $user->id, action: AccessLogActionEnum::SESSION_ACTIVITY_CREATED, medicalRecordId: $record->id);

                return $sessionActivity;
            });
        } catch (\Throwable $t) {
            FileHelper::remove($notesPath);
            FileHelper::remove($contentPath);

            throw $t;
        }

        return ['activity' => $sessionActivity, 'created' => true];
    }

    /**
     * Situação da ativação, para a página de Configurações.
     *
     * @return array{has_key: bool, has_key_pair: bool, has_pin: bool, pin_locked: bool}
     */
    public function activationStatus(User $user): array
    {
        $details = $this->encryptionKeyService->details($user->id);

        return [
            'has_key' => $details !== null,
            'has_key_pair' => ! empty($details['scale_public_key']),
            'has_pin' => $user->session_activity_pin !== null,
            'pin_locked' => $user->session_activity_pin_locked_at !== null,
        ];
    }

    public function updatePin(User $user, string $pin): void
    {
        if (empty($this->encryptionKeyService->details($user->id)['scale_public_key'])) {
            throw new \DomainException(__('session-activities.key_pair_missing'));
        }

        DB::transaction(function () use ($user, $pin) {
            $user->forceFill([
                'session_activity_pin' => Hash::make($pin),
                'session_activity_pin_attempts' => 0,
                'session_activity_pin_locked_at' => null,
            ])->save();

            $this->accessLogService->register(userId: $user->id, action: AccessLogActionEnum::SESSION_ACTIVITY_PIN_CHANGED);
        });
    }

    /**
     * Registros do termômetro para o painel de evolução. O conteúdo é decifrado no navegador.
     *
     * @return array<int, array{uuid: string, occurred_on: string, content_path: string, link: string}>
     */
    public function thermometerByPatient(int $patientId, int $userId): array
    {
        if (! $this->patients->findById(id: $patientId, userId: $userId, cols: ['id'])) {
            throw new \DomainException(__('session-activities.patient_not_found'));
        }

        $activities = $this->sessionActivities->listByPatient(userId: $userId, patientId: $patientId, activity: SessionActivityEnum::THERMOMETER);

        $this->accessLogService->registerMany(
            userId: $userId,
            action: AccessLogActionEnum::SESSION_ACTIVITY_VIEWED,
            medicalRecordIds: $activities->pluck('medical_record_id')->all(),
        );

        return $activities->map(fn (SessionActivities $activity) => [
            'uuid' => $activity->uuid,
            'occurred_on' => $activity->occurred_on->toDateString(),
            'content_path' => $activity->content_path,
            'link' => FileHelper::getLinkTemp(path: $activity->content_path, minutes: self::LINK_MINUTES),
        ])->values()->all();
    }

    /**
     * Sala criada direto pela API tem session_id livre, que pode coincidir com o id de uma consulta de outra pessoa.
     * Por isso o link da consulta precisa apontar para esta sala.
     */
    public function agendaOf(VideoRoom $videoRoom): ?Agenda
    {
        if (! ctype_digit((string) $videoRoom->session_id)) {
            return null;
        }

        $agenda = $this->agenda->newQuery()->select(self::AGENDA_COLUMNS)->find((int) $videoRoom->session_id);

        return $agenda && $agenda->room_link === $this->jitsiRoomService->url($videoRoom) ? $agenda : null;
    }

    public function agendaOrFail(VideoRoom $videoRoom): Agenda
    {
        return $this->agendaOf($videoRoom) ?? throw new \DomainException(__('session-activities.unavailable'));
    }

    private function cannotSaveReason(?Agenda $agenda, ?string $publicKey): ?string
    {
        if (! $agenda) {
            return 'no_agenda';
        }

        if ((string) config('psi_video.activity_draft_secret') === '') {
            return 'not_configured';
        }

        if (! $publicKey) {
            return 'not_activated';
        }

        return $this->planLimitService->hasReached($agenda->user_id, 'total_medical_records') ? 'plan_limit' : null;
    }

    private function checkPin(User $user, string $pin): void
    {
        if ($user->session_activity_pin === null) {
            throw new \DomainException(__('session-activities.pin_not_set'));
        }

        if ($user->session_activity_pin_locked_at !== null) {
            throw new SessionActivityPinException(__('session-activities.pin_locked'), locked: true);
        }

        if (Hash::check($pin, $user->session_activity_pin)) {
            if ($user->session_activity_pin_attempts > 0) {
                $user->forceFill(['session_activity_pin_attempts' => 0])->save();
            }

            return;
        }

        $this->accessLogService->register(userId: $user->id, action: AccessLogActionEnum::SESSION_ACTIVITY_PIN_FAILED);
        $this->users->newQuery()->whereKey($user->id)->increment('session_activity_pin_attempts');

        if ($user->refresh()->session_activity_pin_attempts < self::MAX_PIN_ATTEMPTS) {
            throw new SessionActivityPinException(__('session-activities.pin_invalid'));
        }

        $user->forceFill(['session_activity_pin_locked_at' => now()])->save();
        $this->notifyPinLocked($user);

        throw new SessionActivityPinException(__('session-activities.pin_locked'), locked: true);
    }

    private function notifyPinLocked(User $user): void
    {
        try {
            Mail::to($user->email)->send(new SessionActivityPinLockedMail($user->name, route('session-activities.index')));
        } catch (\Throwable $t) {
            RegisterLog::dispatchSync([
                'message' => $t->getMessage(),
                'method' => 'SessionActivityService->notifyPinLocked',
            ]);
        }
    }

    public function assertGrant(string $grant, VideoRoom $videoRoom, User $user): void
    {
        try {
            $payload = JWT::decode($grant, new Key($this->derivedSecret('grant'), 'HS256'));
        } catch (\Throwable) {
            throw new SessionActivityPinException(__('session-activities.grant_invalid'));
        }

        if (($payload->room ?? null) !== $videoRoom->room || ($payload->uid ?? null) !== $user->id) {
            throw new SessionActivityPinException(__('session-activities.grant_invalid'));
        }
    }

    public function assertWithinDeadline(VideoRoom $videoRoom): void
    {
        if (now()->gt($this->deadline($videoRoom))) {
            throw new \DomainException(__('session-activities.expired'));
        }
    }

    private function deadline(VideoRoom $videoRoom): Carbon
    {
        return $videoRoom->ends_at->copy()->addHours(self::GRACE_HOURS);
    }

    /**
     * Referências opacas do rascunho no navegador: nada de nome, CPF ou id em claro, e nada que ligue duas salas.
     *
     * @return array{psychologist_ref: string, patient_ref: string, expires_at: string}
     */
    private function draftScope(VideoRoom $videoRoom, Agenda $agenda): array
    {
        $secret = $this->derivedSecret('draft');

        return [
            'psychologist_ref' => substr(hash_hmac('sha256', 'psychologist|'.$videoRoom->room.'|'.$agenda->user_id, $secret), 0, 32),
            'patient_ref' => substr(hash_hmac('sha256', 'patient|'.$videoRoom->room.'|'.$agenda->patient_id, $secret), 0, 32),
            'expires_at' => $this->deadline($videoRoom)->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    private function derivedSecret(string $purpose): string
    {
        $secret = (string) config('psi_video.activity_draft_secret');

        if ($secret === '') {
            throw new \DomainException(__('session-activities.unavailable'));
        }

        return hash_hmac('sha256', $purpose, $secret);
    }

    private function catalog(): array
    {
        $catalog = [];

        foreach (SessionActivityEnum::cases() as $activity) {
            $catalog[] = ['slug' => $activity->value, ...config('session_activities.activities.'.$activity->value)];
        }

        return $catalog;
    }
}
