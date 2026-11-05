<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\VideoRoomRoleEnum;
use App\Models\VideoRoom;
use App\Models\VideoRoomParticipant;
use Carbon\Carbon;
use Firebase\JWT\JWT;

/**
 * Emite o JWT aceito pelo Prosody (mod_auth_token) para o participante entrar na sala.
 */
class JitsiTokenIssuer
{
    private const int UNLIMITED_WINDOW_TOKEN_MINUTES = 60;

    /**
     * @return array{jwt: string, expires_at: Carbon}
     */
    public function issue(VideoRoom $room, VideoRoomParticipant $participant): array
    {
        $now = Carbon::now();
        // O Prosody só valida o token na entrada: quem já está na chamada não cai quando ele expira
        $expiresAt = $this->expiresAt($room, $now);
        $isModerator = $participant->role === VideoRoomRoleEnum::MODERATOR;

        $payload = [
            'aud' => config('psi_video.jitsi.aud'),
            'iss' => config('psi_video.jitsi.app_id'),
            'sub' => config('psi_video.jitsi.sub'),
            'room' => $room->room,
            'iat' => $now->getTimestamp(),
            'nbf' => $now->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
            'context' => [
                'user' => [
                    'id' => substr((string) ($participant->cpf_hmac ?? $participant->phone_hmac), 0, 16),
                    'name' => $participant->name,
                    'moderator' => $isModerator,
                    'affiliation' => $isModerator ? 'owner' : 'member',
                ],
            ],
        ];

        return [
            'jwt' => JWT::encode($payload, (string) config('psi_video.jitsi.app_secret'), 'HS256'),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Sem limite de fechamento, o token vale até o fim da sessão (ou a partir de agora, se já passou) mais uma folga,
     * para não emitir token sem prazo.
     */
    private function expiresAt(VideoRoom $room, Carbon $now): Carbon
    {
        $closesAfter = config('psi_video.access_closes_after_minutes');

        if ($closesAfter !== null) {
            return $room->ends_at->copy()->utc()->addMinutes((int) $closesAfter);
        }

        return $room->ends_at->copy()->utc()->max($now->copy()->utc())->addMinutes(self::UNLIMITED_WINDOW_TOKEN_MINUTES);
    }
}
