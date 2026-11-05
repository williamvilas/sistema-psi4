<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationInvitations extends Model
{
    protected $table = 'registration_invitations';

    protected $fillable = [
        'token_hash',
        'lead_id',
        'source',
        'expires_at',
        'used_at',
        'used_by_user_id',
    ];

    protected $casts = [
        'lead_id' => 'integer',
        'used_by_user_id' => 'integer',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function existsValidByTokenHash(string $tokenHash): bool
    {
        return self::query()
            ->where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Consome o convite de forma atômica: o UPDATE condicional garante que duas
     * requisições simultâneas não usem o mesmo convite. Retorna null se inválido.
     */
    public function consumeByTokenHash(string $tokenHash, int $userId): ?self
    {
        $affected = self::query()
            ->where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now(), 'used_by_user_id' => $userId]);

        if ($affected !== 1) {
            return null;
        }

        return self::query()->where('token_hash', $tokenHash)->first();
    }
}
