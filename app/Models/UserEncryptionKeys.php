<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEncryptionKeys extends Model
{
    protected $table = 'user_encryption_keys';

    protected $fillable = [
        'user_id',
        'passphrase_wrapped_key',
        'passphrase_salt',
        'passphrase_iterations',
        'recovery_wrapped_key',
        'recovery_salt',
        'recovery_iterations',
        'key_check',
        'scale_public_key',
        'scale_wrapped_private_key',
    ];

    protected $hidden = [
        'key_check',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'passphrase_iterations' => 'integer',
        'recovery_iterations' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function findByUser(int $userId, array $cols = ['*']): ?self
    {
        return self::query()->select($cols)->where('user_id', $userId)->first();
    }

    public function existsByUser(int $userId): bool
    {
        return self::query()->where('user_id', $userId)->exists();
    }

    public function findScalePublicKeyByUser(int $userId): ?string
    {
        return self::query()->where('user_id', $userId)->value('scale_public_key');
    }

    /**
     * Grava o par de chaves das escalas uma única vez: trocar a pública deixaria respostas antigas ilegíveis.
     */
    public function storeScaleKeyPair(int $userId, string $publicKey, string $wrappedPrivateKey): int
    {
        return self::query()
            ->where('user_id', $userId)
            ->whereNull('scale_public_key')
            ->update([
                'scale_public_key' => $publicKey,
                'scale_wrapped_private_key' => $wrappedPrivateKey,
                'updated_at' => now(),
            ]);
    }
}
