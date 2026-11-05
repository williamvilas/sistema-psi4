<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccessLogActionEnum;
use App\Models\UserEncryptionKeys;
use Illuminate\Support\Facades\DB;

/**
 * Guarda a chave dos prontuários embrulhada (criptografia de ponta a ponta).
 * O servidor nunca recebe a senha mestre, a chave de recuperação nem a chave em claro:
 * só os embrulhos e o key_check (SHA-256 da chave), usado para provar a posse da chave
 * antes de substituir um embrulho.
 */
readonly class EncryptionKeyService
{
    public function __construct(
        private UserEncryptionKeys $userEncryptionKeys,
        private AccessLogService $accessLogService,
    ) {
    }

    public function exists(int $userId): bool
    {
        return $this->userEncryptionKeys->existsByUser($userId);
    }

    public function details(int $userId): ?array
    {
        $key = $this->userEncryptionKeys->findByUser(userId: $userId, cols: [
            'passphrase_wrapped_key',
            'passphrase_salt',
            'passphrase_iterations',
            'recovery_wrapped_key',
            'recovery_salt',
            'recovery_iterations',
            'scale_public_key',
            'scale_wrapped_private_key',
        ]);

        return $key?->toArray();
    }

    public function store(int $userId, array $data): void
    {
        if ($this->exists($userId)) {
            throw new \DomainException(__('encryption-key.already_exists'));
        }

        DB::transaction(function () use ($userId, $data) {
            $this->userEncryptionKeys->create([
                'user_id' => $userId,
                'passphrase_wrapped_key' => $data['passphrase_wrapped_key'],
                'passphrase_salt' => $data['passphrase_salt'],
                'passphrase_iterations' => $data['passphrase_iterations'],
                'recovery_wrapped_key' => $data['recovery_wrapped_key'],
                'recovery_salt' => $data['recovery_salt'],
                'recovery_iterations' => $data['recovery_iterations'],
                'key_check' => $this->hashKeyCheck($data['key_check']),
            ]);

            $this->accessLogService->register(userId: $userId, action: AccessLogActionEnum::ENCRYPTION_KEY_CREATED);
        });
    }

    /**
     * Par de chaves das escalas respondidas por link, gerado no navegador na primeira vez que a psicóloga cria um link.
     * A privada chega embrulhada pela chave dos prontuários; o servidor nunca a vê em claro.
     */
    public function storeScaleKeyPair(int $userId, string $publicKey, string $wrappedPrivateKey): void
    {
        if (! $this->exists($userId)) {
            throw new \DomainException(__('encryption-key.not_found'));
        }

        if (! $this->userEncryptionKeys->storeScaleKeyPair(userId: $userId, publicKey: $publicKey, wrappedPrivateKey: $wrappedPrivateKey)) {
            throw new \DomainException(__('scales.scale_key_already_exists'));
        }
    }

    public function updatePassphrase(int $userId, array $data): void
    {
        $this->updateWrap(userId: $userId, keyCheck: $data['key_check'], action: AccessLogActionEnum::ENCRYPTION_KEY_PASSPHRASE_CHANGED, values: [
            'passphrase_wrapped_key' => $data['passphrase_wrapped_key'],
            'passphrase_salt' => $data['passphrase_salt'],
            'passphrase_iterations' => $data['passphrase_iterations'],
        ]);
    }

    public function updateRecovery(int $userId, array $data): void
    {
        $this->updateWrap(userId: $userId, keyCheck: $data['key_check'], action: AccessLogActionEnum::ENCRYPTION_KEY_RECOVERY_CHANGED, values: [
            'recovery_wrapped_key' => $data['recovery_wrapped_key'],
            'recovery_salt' => $data['recovery_salt'],
            'recovery_iterations' => $data['recovery_iterations'],
        ]);
    }

    private function updateWrap(int $userId, string $keyCheck, AccessLogActionEnum $action, array $values): void
    {
        DB::transaction(function () use ($userId, $keyCheck, $action, $values) {
            $key = $this->userEncryptionKeys->findByUser($userId);

            if (! $key) {
                throw new \DomainException(__('encryption-key.not_found'));
            }

            // Sem a chave correta, um novo embrulho deixaria os prontuários ilegíveis.
            if (! hash_equals($key->key_check, $this->hashKeyCheck($keyCheck))) {
                throw new \DomainException(__('encryption-key.invalid_key'));
            }

            $key->fill($values);

            if (! $key->save()) {
                throw new \DomainException(__('errors.update_failed'));
            }

            $this->accessLogService->register(userId: $userId, action: $action);
        });
    }

    private function hashKeyCheck(string $keyCheck): string
    {
        return hash('sha256', strtolower($keyCheck));
    }
}
