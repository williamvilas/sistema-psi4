<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\VideoRoomRoleEnum;
use App\Enums\VideoRoomStatusEnum;
use App\Exceptions\VideoRoomAccessDeniedException;
use App\Exceptions\VideoRoomOutsideWindowException;
use App\Models\VideoRoom;
use App\Models\VideoRoomParticipant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Salas de vídeo no Jitsi com acesso por CPF (ou telefone, para o paciente sem CPF). O Laravel é a fonte da verdade
 * das salas: o Jitsi só troca o dado digitado por um JWT emitido aqui (access()).
 */
readonly class JitsiRoomService
{
    public function __construct(
        private CpfHasher $cpfHasher,
        private PhoneHasher $phoneHasher,
        private RoomIdGenerator $roomIdGenerator,
        private JitsiTokenIssuer $tokenIssuer,
    ) {
    }

    public function findByRoom(string $room): VideoRoom
    {
        return VideoRoom::query()->where('room', $room)->with('participants')->firstOrFail();
    }

    /**
     * @param  array{session_id?: ?string, starts_at: string, ends_at: string, participants: array<int, array{cpf?: ?string, phone?: ?string, name: string, role: string}>}  $data
     */
    public function store(array $data): VideoRoom
    {
        return DB::transaction(function () use ($data) {
            $videoRoom = VideoRoom::query()->create([
                'room' => $this->roomIdGenerator->generate(),
                'session_id' => $data['session_id'] ?? null,
                'starts_at' => Carbon::parse($data['starts_at'])->utc(),
                'ends_at' => Carbon::parse($data['ends_at'])->utc(),
                'status' => VideoRoomStatusEnum::ACTIVE,
            ]);

            $this->replaceParticipants($videoRoom, $data['participants']);

            return $videoRoom->load('participants');
        });
    }

    /**
     * Reagenda e/ou substitui a lista inteira de participantes. O room (e portanto a url) não muda.
     *
     * @param  array{starts_at?: string, ends_at?: string, participants?: array<int, array{cpf?: ?string, phone?: ?string, name: string, role: string}>}  $data
     */
    public function update(VideoRoom $videoRoom, array $data): VideoRoom
    {
        return DB::transaction(function () use ($videoRoom, $data) {
            if (isset($data['starts_at'])) {
                $videoRoom->starts_at = Carbon::parse($data['starts_at'])->utc();
            }

            if (isset($data['ends_at'])) {
                $videoRoom->ends_at = Carbon::parse($data['ends_at'])->utc();
            }

            $videoRoom->save();

            if (isset($data['participants'])) {
                $videoRoom->participants()->delete();
                $this->replaceParticipants($videoRoom, $data['participants']);
            }

            return $videoRoom->load('participants');
        });
    }

    public function cancel(VideoRoom $videoRoom): void
    {
        $videoRoom->update(['status' => VideoRoomStatusEnum::CANCELLED]);
    }

    /**
     * Troca o CPF ou o telefone digitado no Jitsi por um JWT do Prosody.
     *
     * @return array{jwt: string, name: string, role: string, expires_at: Carbon}
     *
     * @throws VideoRoomAccessDeniedException dado inválido, sala inexistente/cancelada ou dado fora da lista
     * @throws VideoRoomOutsideWindowException antes de abrir ou depois de fechar a janela de acesso
     */
    public function access(string $room, ?string $cpf = null, ?string $phone = null): array
    {
        $credential = $this->credential($cpf, $phone);

        $videoRoom = $credential === null ? null : VideoRoom::query()
            ->where('room', $room)
            ->where('status', VideoRoomStatusEnum::ACTIVE)
            ->first();

        $participant = $videoRoom === null ? null : VideoRoomParticipant::query()
            ->where('video_room_id', $videoRoom->id)
            ->where($credential)
            ->first();

        if ($participant === null) {
            $this->logAccess($room, 'denied');

            throw new VideoRoomAccessDeniedException(__('video-room.unauthorized_access'));
        }

        // Limite não configurado (null) libera a entrada a qualquer horário daquele lado da janela
        $opensBefore = config('psi_video.access_opens_before_minutes');
        $closesAfter = config('psi_video.access_closes_after_minutes');
        $now = Carbon::now();

        if ($opensBefore !== null && $now->lt($videoRoom->starts_at->copy()->subMinutes((int) $opensBefore))) {
            $this->logAccess($room, 'outside_window');

            throw new VideoRoomOutsideWindowException(__('video-room.not_started', ['minutes' => (int) $opensBefore]));
        }

        if ($closesAfter !== null && $now->gt($videoRoom->ends_at->copy()->addMinutes((int) $closesAfter))) {
            $this->logAccess($room, 'outside_window');

            throw new VideoRoomOutsideWindowException(__('video-room.finished'));
        }

        $token = $this->tokenIssuer->issue($videoRoom, $participant);

        $this->logAccess($room, 'granted');

        return [
            'jwt' => $token['jwt'],
            'name' => $participant->name,
            'role' => $participant->role->value,
            'expires_at' => $token['expires_at'],
        ];
    }

    public function url(VideoRoom $videoRoom): string
    {
        return config('psi_video.jitsi_base_url').'/'.$videoRoom->room;
    }

    /**
     * Filtro do participante pelo dado digitado. O telefone só encontra convidado: é ele que impede
     * o WhatsApp da psicóloga, que os pacientes costumam ter, de abrir a sala como moderadora.
     *
     * @return array<string, string>|null
     */
    private function credential(?string $cpf, ?string $phone): ?array
    {
        if ($cpf !== null) {
            $normalized = $this->cpfHasher->normalize($cpf);

            return $normalized === null ? null : ['cpf_hmac' => $this->cpfHasher->hmac($normalized)];
        }

        $e164 = $this->phoneHasher->normalize((string) $phone);

        return $e164 === null ? null : ['phone_hmac' => $this->phoneHasher->hmac($e164), 'role' => VideoRoomRoleEnum::GUEST->value];
    }

    /**
     * Cada participante guarda só um dado de entrada: o CPF ou, sem ele, o telefone.
     *
     * @param  array<int, array{cpf?: ?string, phone?: ?string, name: string, role: string}>  $participants
     */
    private function replaceParticipants(VideoRoom $videoRoom, array $participants): void
    {
        foreach ($participants as $participant) {
            $cpf = $this->cpfHasher->normalize((string) ($participant['cpf'] ?? ''));

            $videoRoom->participants()->create([
                'cpf_hmac' => $cpf === null ? null : $this->cpfHasher->hmac($cpf),
                'phone_hmac' => $cpf === null ? $this->phoneHasher->hmac((string) $this->phoneHasher->normalize((string) $participant['phone'])) : null,
                'name' => $participant['name'],
                'role' => $participant['role'],
            ]);
        }
    }

    /**
     * Nunca registrar CPF, telefone, HMAC ou JWT.
     */
    private function logAccess(string $room, string $result): void
    {
        Log::info('Acesso à sala de vídeo', ['room' => $room, 'result' => $result, 'ip' => request()->ip()]);
    }
}
