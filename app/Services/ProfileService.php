<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class ProfileService
{
    public function __construct(private readonly AgendaVideoRoomService $agendaVideoRoomService = new AgendaVideoRoomService)
    {
    }

    /**
     * Atualiza os dados pessoais do usuário. O CPF, depois de informado, não pode mais ser alterado
     * e a troca de e-mail invalida a verificação anterior.
     */
    public function update(User $user, array $payload): User
    {
        if (! empty($user->document)) {
            unset($payload['document']);
        }

        $user->fill($payload);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if (! $user->save()) {
            throw new \DomainException(__('messages.failed_update'));
        }

        // A psicóloga entra nas salas das próximas consultas online pelo CPF. O WhatsApp conta porque o
        // telefone do paciente sem CPF não pode ser o dela
        if ($user->wasChanged(['document', 'whatsapp', 'name'])) {
            $this->agendaVideoRoomService->refreshParticipantsBy('user_id', $user->id);
        }

        return $user;
    }
}
