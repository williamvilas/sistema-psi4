<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccessLogActionEnum;
use App\Helpers\CacheHelper;
use App\Models\Patients;
use App\Models\VideoRoom;
use Illuminate\Support\Str;

/**
 * Autoriza o Jitsi a gravar sem nova senha. A sala pede, e a psicóloga aprova no PSI4, com login e senha mestre. A senha
 * e a chave ficam só no navegador dela, nunca no Jitsi: o JWT da sala prova apenas o CPF, então a chave embrulhada
 * entregue a ele poderia ser atacada fora do sistema. O servidor confere um desafio ECDH, que só quem abriu a chave
 * privada responde. O pedido fica só no cache e a autorização é entregue uma vez.
 */
readonly class SessionAuthorizationService
{
    private const int TTL_SECONDS = 300;

    private const string STATUS_PENDING = 'pending';

    private const string STATUS_APPROVED = 'approved';

    private const string PROOF_INFO = 'psi4-room-authorization';

    public function __construct(
        private SessionActivityService $sessionActivityService,
        private EncryptionKeyService $encryptionKeyService,
        private AccessLogService $accessLogService,
        private Patients $patients,
    ) {
    }

    /**
     * @return array{id: string, url: string, expires_in: int}
     */
    public function request(VideoRoom $videoRoom): array
    {
        $agenda = $this->sessionActivityService->agendaOrFail($videoRoom);
        $this->sessionActivityService->assertWithinDeadline($videoRoom);

        if (empty($this->encryptionKeyService->details($agenda->user_id)['scale_public_key'])) {
            throw new \DomainException(__('session-activities.key_pair_missing'));
        }

        $serverKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);

        if ($serverKey === false || ! openssl_pkey_export($serverKey, $serverPrivateKey)) {
            throw new \DomainException(__('session-activities.authorization_failed'));
        }

        $ec = openssl_pkey_get_details($serverKey)['ec'];
        $id = Str::random(64);

        $this->remember($id, [
            'status' => self::STATUS_PENDING,
            'room' => $videoRoom->room,
            'user_id' => $agenda->user_id,
            'patient_id' => $agenda->patient_id,
            'scheduled_on' => $agenda->scheduled_on->getTimestamp(),
            'server_private_key' => $serverPrivateKey,
            'server_public_key' => base64_encode("\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)),
            'expires_at' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
        ]);

        return [
            'id' => $id,
            'url' => route('video-room-authorizations.show', ['id' => $id]),
            'expires_in' => self::TTL_SECONDS,
        ];
    }

    /**
     * O que a tela mostra para ela conferir de qual sessão é o pedido antes de aprovar. Null quando venceu ou não é dela.
     *
     * @return array{id: string, patient: string, scheduled_on: int, server_public_key: string, info: string, approved: bool}|null
     */
    public function pendingFor(string $id, int $userId): ?array
    {
        $entry = $this->entry($id);

        if (! $entry || $entry['user_id'] !== $userId) {
            return null;
        }

        return [
            'id' => $id,
            'patient' => (string) $this->patients->newQuery()->whereKey($entry['patient_id'])->value('name'),
            'scheduled_on' => $entry['scheduled_on'],
            'server_public_key' => $entry['server_public_key'],
            'info' => self::PROOF_INFO.'|'.$id,
            'approved' => $entry['status'] === self::STATUS_APPROVED,
        ];
    }

    public function approve(string $id, int $userId, string $proof): void
    {
        $entry = $this->entry($id);

        if (! $entry || $entry['user_id'] !== $userId) {
            throw new \DomainException(__('session-activities.authorization_expired'));
        }

        if (! $this->isValidProof($entry, $id, $proof)) {
            throw new \DomainException(__('session-activities.authorization_invalid'));
        }

        $entry['status'] = self::STATUS_APPROVED;
        $this->remember($id, $entry);

        $this->accessLogService->register(userId: $userId, action: AccessLogActionEnum::SESSION_ACTIVITY_AUTHORIZED);
    }

    /**
     * O Jitsi consulta até ser aprovado. A autorização é emitida aqui, para não ficar guardada no cache.
     *
     * @return array{status: string, grant?: string, draft?: array<string, string>}
     */
    public function result(VideoRoom $videoRoom, string $id): array
    {
        $entry = $this->entry($id);

        if (! $entry || $entry['room'] !== $videoRoom->room) {
            throw new \DomainException(__('session-activities.authorization_expired'));
        }

        if ($entry['status'] !== self::STATUS_APPROVED) {
            return ['status' => self::STATUS_PENDING];
        }

        CacheHelper::forget($this->cacheKey($id));

        return ['status' => self::STATUS_APPROVED, ...$this->sessionActivityService->issueAuthorization($videoRoom)];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function isValidProof(array $entry, string $id, string $proof): bool
    {
        $publicKey = $this->encryptionKeyService->details($entry['user_id'])['scale_public_key'] ?? null;
        $peer = $publicKey ? openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".chunk_split($publicKey, 64, "\n").'-----END PUBLIC KEY-----') : false;
        $serverKey = openssl_pkey_get_private($entry['server_private_key']);

        if ($peer === false || $serverKey === false) {
            return false;
        }

        $secret = openssl_pkey_derive($peer, $serverKey, 32);

        return $secret !== false
            && hash_equals(base64_encode(hash_hmac('sha256', self::PROOF_INFO.'|'.$id, $secret, true)), $proof);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function entry(string $id): ?array
    {
        $entry = CacheHelper::get($this->cacheKey($id));

        return is_array($entry) && $entry['expires_at'] >= now()->getTimestamp() ? $entry : null;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function remember(string $id, array $entry): void
    {
        CacheHelper::put($this->cacheKey($id), $entry, max(1, $entry['expires_at'] - now()->getTimestamp()));
    }

    private function cacheKey(string $id): string
    {
        return 'video-room-authorization:'.hash('sha256', $id);
    }
}
