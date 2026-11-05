<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ExpiredFlowException;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function __construct(private User $user)
    {
    }

    public function sendResetLink(string $email): void
    {
        if (! $this->user->findByEmail($email)) {
            throw new \DomainException(__('password-reset.user_not_found'));
        }

        $status = Password::sendResetLink(['email' => $email]);

        if ($status === Password::RESET_THROTTLED) {
            throw new \DomainException(__('password-reset.throttled'));
        }

        if ($status !== Password::RESET_LINK_SENT) {
            throw new \DomainException(__('password-reset.user_not_found'));
        }

        Log::info('Link de redefinição de senha enviado por e-mail', ['ip' => request()->ip()]);
    }

    /**
     * A troca da senha invalida as demais sessões (middleware AuthenticateSession).
     */
    public function reset(array $credentials): void
    {
        $status = Password::reset(
            $credentials,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'force_change_password' => 0,
                    'temporary_password_expiration' => null,
                ])->save();

                event(new PasswordReset($user));

                Log::info('Senha redefinida via link de e-mail', ['user_id' => $user->id, 'ip' => request()->ip()]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new ExpiredFlowException(__('password-reset.invalid_token'));
        }
    }
}
