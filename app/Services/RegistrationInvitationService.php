<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RegistrationInvitations;
use App\Models\RegistrationLeads;
use App\Models\User;
use Illuminate\Support\Str;

class RegistrationInvitationService
{
    public function __construct(
        private RegistrationInvitations $invitations,
        private RegistrationLeads $leads,
    ) {
    }

    public function isInviteOnly(): bool
    {
        return (bool) config('registration.invite_only');
    }

    /**
     * Gera um convite de uso único. O token em claro só existe no retorno.
     */
    public function create(?int $leadId = null, ?string $source = null): array
    {
        $token = Str::random(40);

        $invitation = $this->invitations->create([
            'token_hash' => $this->hash($token),
            'lead_id' => $leadId,
            'source' => $source,
            'expires_at' => now()->addDays((int) config('registration.invite_ttl_days')),
        ]);

        if ($leadId) {
            $this->leads->updateStatusById($leadId, RegistrationLeads::STATUS_INVITED);
        }

        return ['token' => $token, 'invitation' => $invitation];
    }

    /**
     * Guarda na sessão o convite recebido pelo link, se ele ainda for válido.
     */
    public function rememberFromToken(string $token): bool
    {
        if (! $this->invitations->existsValidByTokenHash($this->hash($token))) {
            return false;
        }

        session()->put(config('registration.session_key'), $token);

        return true;
    }

    public function hasValidInSession(): bool
    {
        $token = session(config('registration.session_key'));

        return is_string($token) && $this->invitations->existsValidByTokenHash($this->hash($token));
    }

    /**
     * Deve rodar dentro da transação que cria o usuário: se o convite não for mais
     * válido, a exceção desfaz a criação da conta.
     */
    public function consumeForUser(User $user): void
    {
        if (! $this->isInviteOnly()) {
            return;
        }

        $token = session(config('registration.session_key'));

        $invitation = is_string($token)
            ? $this->invitations->consumeByTokenHash($this->hash($token), $user->id)
            : null;

        if (! $invitation) {
            throw new \DomainException(__(is_string($token) ? 'auth.invite_invalid' : 'auth.invite_required'));
        }

        session()->forget(config('registration.session_key'));

        if ($invitation->lead_id) {
            $this->leads->updateStatusById($invitation->lead_id, RegistrationLeads::STATUS_REGISTERED);
        }
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
