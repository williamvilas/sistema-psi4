<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\VideoRoomRoleEnum;
use App\Enums\VideoRoomStatusEnum;
use App\Models\VideoRoom;
use App\Models\VideoRoomParticipant;
use Closure;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticação das dinâmicas pelo JWT da sala (Bearer), emitido em /api/video-room/access. Só moderador passa.
 * O token prova apenas o CPF: não abre nada de clínico, e gravar também exige a aprovação no PSI4 (SessionAuthorizationService).
 */
class AuthenticateVideoRoomJwt
{
    // A sessão pode passar do exp do token, e um registro não pode se perder por isso.
    private const int EXPIRED_GRACE_SECONDS = 21600;

    public const string ATTRIBUTE = 'video_room';

    public const string PARTICIPANT_REF = 'video_room_participant_ref';

    public const string GUEST = 'guest';

    /**
     * @param  string|null  $audience  self::GUEST libera só o convidado, para o consentimento da transcrição: é ele quem decide.
     */
    public function handle(Request $request, Closure $next, ?string $audience = null): Response
    {
        $payload = $this->decode((string) $request->bearerToken());
        $videoRoom = $payload ? $this->videoRoomFor($payload, $audience === self::GUEST) : null;

        if (! $videoRoom) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set(self::ATTRIBUTE, $videoRoom);
        $request->attributes->set(self::PARTICIPANT_REF, $payload->context->user->id);

        return $next($request);
    }

    private function decode(string $token): ?object
    {
        $secret = (string) config('psi_video.jitsi.app_secret');

        if ($token === '' || $secret === '') {
            return null;
        }

        // Não usar JWT::$leeway: é estático, e o processo atende várias psicólogas (Octane).
        try {
            return JWT::decode($token, new Key($secret, 'HS256'));
        } catch (ExpiredException $e) {
            $payload = $e->getPayload();

            return now()->getTimestamp() - (int) ($payload->exp ?? 0) <= self::EXPIRED_GRACE_SECONDS ? $payload : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function videoRoomFor(object $payload, bool $guest = false): ?VideoRoom
    {
        $user = $payload->context->user ?? null;

        if (($payload->iss ?? null) !== config('psi_video.jitsi.app_id')
            || ($payload->aud ?? null) !== config('psi_video.jitsi.aud')
            || ($user->moderator ?? null) !== ! $guest
            || ! is_string($user->id ?? null)
            || ! is_string($payload->room ?? null)) {
            return null;
        }

        $videoRoom = VideoRoom::query()
            ->where('room', $payload->room)
            ->where('status', VideoRoomStatusEnum::ACTIVE)
            ->with('participants')
            ->first();

        // A lista de participantes pode ter mudado depois de o token ser emitido.
        $role = $guest ? VideoRoomRoleEnum::GUEST : VideoRoomRoleEnum::MODERATOR;
        $stillAllowed = $videoRoom?->participants->contains(fn (VideoRoomParticipant $participant) => $participant->role === $role
            && hash_equals(substr((string) ($guest ? ($participant->cpf_hmac ?? $participant->phone_hmac) : $participant->cpf_hmac), 0, 16), $user->id));

        return $stillAllowed ? $videoRoom : null;
    }
}
