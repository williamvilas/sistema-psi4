<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ExpiredFlowException;
use App\Mail\TwoFactorResetMail;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public const CHALLENGE_SESSION_KEY = 'two_factor_challenge';
    public const CHALLENGE_TTL_MINUTES = 5;
    public const RESET_LINK_TTL_MINUTES = 60;
    private const RECOVERY_CODES_AMOUNT = 8;
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private User $user,
        private Google2FA $google2fa,
    ) {
    }

    public function getStatus(User $user): array
    {
        $enabled = $user->hasTwoFactorEnabled();

        return [
            'enabled' => $enabled,
            'confirmed_at' => $enabled ? $user->two_factor_confirmed_at : null,
            'recovery_codes_left' => $enabled ? count($user->two_factor_recovery_codes ?? []) : 0,
        ];
    }

    public function setup(User $user): array
    {
        if ($user->hasTwoFactorEnabled()) {
            throw new \DomainException(__('two-factor.already_enabled'));
        }

        $secret = $this->google2fa->generateSecretKey(32);
        $user->savePendingTwoFactorSecret($secret);

        return [
            'qr_code' => $this->makeQrCodeSvg($user->email, $secret),
            'secret' => trim(chunk_split($secret, 4, ' ')),
        ];
    }

    public function confirm(User $user, string $code): array
    {
        if ($user->hasTwoFactorEnabled()) {
            throw new \DomainException(__('two-factor.already_enabled'));
        }

        if (empty($user->two_factor_secret)) {
            throw new \DomainException(__('two-factor.setup_not_started'));
        }

        $this->ensureIsNotRateLimited('confirm|'.$user->id);

        $step = $this->google2fa->verifyKeyNewer($user->two_factor_secret, $code, 0);

        if ($step === false) {
            RateLimiter::hit('two-factor|confirm|'.$user->id, 60);

            throw new \DomainException(__('two-factor.invalid_code'));
        }

        RateLimiter::clear('two-factor|confirm|'.$user->id);

        $recoveryCodes = $this->generateRecoveryCodes();
        $user->enableTwoFactor($this->hashRecoveryCodes($recoveryCodes), (int) $step);

        $this->audit('Autenticação de 2 fatores ativada', $user);

        return $recoveryCodes;
    }

    public function disable(User $user, string $code): void
    {
        $this->ensureReauthenticated($user, $code);

        $user->disableTwoFactor();

        $this->audit('Autenticação de 2 fatores desativada', $user);
    }

    public function regenerateRecoveryCodes(User $user, string $code): array
    {
        $this->ensureReauthenticated($user, $code);

        $recoveryCodes = $this->generateRecoveryCodes();
        $user->updateTwoFactorRecoveryCodes($this->hashRecoveryCodes($recoveryCodes));

        $this->audit('Códigos de recuperação do 2FA regenerados', $user);

        return $recoveryCodes;
    }

    public function startChallenge(User $user, bool $remember): void
    {
        session()->put(self::CHALLENGE_SESSION_KEY, [
            'user_id' => $user->id,
            'remember' => $remember,
            'expires_at' => now()->addMinutes(self::CHALLENGE_TTL_MINUTES)->timestamp,
        ]);
    }

    public function getPendingChallenge(): ?array
    {
        $challenge = session(self::CHALLENGE_SESSION_KEY);

        if (empty($challenge) || $challenge['expires_at'] < now()->timestamp) {
            session()->forget(self::CHALLENGE_SESSION_KEY);

            return null;
        }

        return $challenge;
    }

    public function finishChallenge(string $code): User
    {
        $challenge = $this->getPendingChallenge();

        if (! $challenge) {
            throw new ExpiredFlowException(__('two-factor.expired_challenge'));
        }

        $user = $this->user->findById((int) $challenge['user_id']);

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            session()->forget(self::CHALLENGE_SESSION_KEY);

            throw new ExpiredFlowException(__('two-factor.expired_challenge'));
        }

        $key = 'challenge|'.$user->id.'|'.request()->ip();
        $this->ensureIsNotRateLimited($key);

        if (! $this->verifyCode($user, $code)) {
            RateLimiter::hit('two-factor|'.$key, 60);

            throw new \DomainException(__('two-factor.invalid_code'));
        }

        RateLimiter::clear('two-factor|'.$key);

        return $user;
    }

    public function sendResetLink(): void
    {
        $challenge = $this->getPendingChallenge();

        if (! $challenge) {
            throw new ExpiredFlowException(__('two-factor.expired_challenge'));
        }

        $user = $this->user->findById((int) $challenge['user_id']);

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            return;
        }

        $url = URL::temporarySignedRoute(
            'two-factor.reset.show',
            now()->addMinutes(self::RESET_LINK_TTL_MINUTES),
            ['user' => $user->id, 'token' => $this->makeResetToken($user)]
        );

        Mail::to($user->email)->send(new TwoFactorResetMail($user->name, $url, self::RESET_LINK_TTL_MINUTES));

        $this->audit('Link de recuperação do 2FA enviado por e-mail', $user);
    }

    public function resetByLink(int $userId, string $token): void
    {
        $user = $this->user->findById($userId);

        if (! $user || ! $user->hasTwoFactorEnabled() || ! hash_equals($this->makeResetToken($user), $token)) {
            throw new ExpiredFlowException(__('two-factor.reset_link_invalid'));
        }

        $user->disableTwoFactor();

        $this->audit('Autenticação de 2 fatores desativada via link de e-mail', $user);
    }

    public function isResetLinkValid(int $userId, string $token): bool
    {
        $user = $this->user->findById($userId);

        return $user && $user->hasTwoFactorEnabled() && hash_equals($this->makeResetToken($user), $token);
    }

    private function verifyCode(User $user, string $code): bool
    {
        $code = trim($code);

        if (preg_match('/^\d{6}$/', $code)) {
            return $this->verifyTotp($user, $code);
        }

        return $this->consumeRecoveryCode($user, $code);
    }

    private function verifyTotp(User $user, string $code): bool
    {
        $step = $this->google2fa->verifyKeyNewer(
            $user->two_factor_secret,
            $code,
            (int) $user->two_factor_last_used_step
        );

        if ($step === false) {
            return false;
        }

        $user->updateTwoFactorLastUsedStep((int) $step);

        return true;
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $code = Str::lower($code);
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $hash) {
            if (Hash::check($code, $hash)) {
                unset($codes[$index]);
                $user->updateTwoFactorRecoveryCodes(array_values($codes));

                $this->audit('Código de recuperação do 2FA utilizado', $user);

                return true;
            }
        }

        return false;
    }

    private function ensureReauthenticated(User $user, string $code, string $context = 'reauth'): void
    {
        if (! $user->hasTwoFactorEnabled()) {
            throw new \DomainException(__('two-factor.not_enabled'));
        }

        $key = $context.'|'.$user->id;
        $this->ensureIsNotRateLimited($key);

        if (! $this->verifyCode($user, $code)) {
            RateLimiter::hit('two-factor|'.$key, 60);

            throw new \DomainException(__('two-factor.invalid_code'));
        }

        RateLimiter::clear('two-factor|'.$key);
    }

    private function ensureIsNotRateLimited(string $key): void
    {
        if (RateLimiter::tooManyAttempts('two-factor|'.$key, self::MAX_ATTEMPTS)) {
            throw new \DomainException(__('two-factor.too_many_attempts'));
        }
    }

    private function generateRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODES_AMOUNT))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    private function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $code) => Hash::make($code), $codes);
    }

    private function makeResetToken(User $user): string
    {
        return hash_hmac('sha256', $user->id.'|'.$user->two_factor_secret, (string) config('app.key'));
    }

    private function makeQrCodeSvg(string $email, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl(config('app.name'), $email, $secret);

        $writer = new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd()));

        return $writer->writeString($url);
    }

    private function audit(string $message, User $user): void
    {
        Log::info($message, ['user_id' => $user->id, 'ip' => request()->ip()]);
    }
}
