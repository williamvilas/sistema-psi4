<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Mail\TwoFactorResetMail;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorServiceTest extends TestCase
{
    use DatabaseTransactions;

    private TwoFactorService $service;
    private Google2FA $google2fa;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->google2fa = new Google2FA();
        $this->service = new TwoFactorService(new User(), $this->google2fa);
        $this->user = User::factory()->create();
    }

    private function enableTwoFactor(): array
    {
        $this->service->setup($this->user);
        $codes = $this->service->confirm($this->user, $this->google2fa->getCurrentOtp($this->user->two_factor_secret));

        return $codes;
    }

    public function testItGetStatusWhenTwoFactorIsDisabled(): void
    {
        $status = $this->service->getStatus($this->user);

        $this->assertSame(['enabled' => false, 'confirmed_at' => null, 'recovery_codes_left' => 0], $status);
    }

    public function testItGetStatusIgnoresPendingSetup(): void
    {
        $this->service->setup($this->user);

        $status = $this->service->getStatus($this->user);

        $this->assertFalse($status['enabled']);
        $this->assertNull($status['confirmed_at']);
        $this->assertSame(0, $status['recovery_codes_left']);
    }

    public function testItGetStatusWhenTwoFactorIsEnabled(): void
    {
        $codes = $this->enableTwoFactor();

        $status = $this->service->getStatus($this->user);

        $this->assertTrue($status['enabled']);
        $this->assertNotNull($status['confirmed_at']);
        $this->assertSame(count($codes), $status['recovery_codes_left']);
    }

    public function testItGetStatusCountsRemainingRecoveryCodesAfterUse(): void
    {
        $codes = $this->enableTwoFactor();
        $this->service->startChallenge($this->user, false);
        $this->service->finishChallenge($codes[0]);

        $status = $this->service->getStatus($this->user->refresh());

        $this->assertSame(count($codes) - 1, $status['recovery_codes_left']);
    }

    public function testItSetupGeneratesSecretAndQrCodeWithoutEnabling(): void
    {
        $result = $this->service->setup($this->user);

        $this->user->refresh();
        $this->assertStringContainsString('<svg', $result['qr_code']);
        $this->assertNotEmpty($result['secret']);
        $this->assertNotEmpty($this->user->two_factor_secret);
        $this->assertFalse($this->user->hasTwoFactorEnabled());
    }

    public function testItSetupThrowsExceptionWhenAlreadyEnabled(): void
    {
        $this->enableTwoFactor();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('two-factor.already_enabled'));

        $this->service->setup($this->user);
    }

    public function testItConfirmEnablesTwoFactorAndReturnsRecoveryCodes(): void
    {
        $codes = $this->enableTwoFactor();

        $this->user->refresh();
        $this->assertTrue($this->user->hasTwoFactorEnabled());
        $this->assertNotNull($this->user->two_factor_confirmed_at);
        $this->assertCount(8, $codes);
        $this->assertCount(8, $this->user->two_factor_recovery_codes);
        $this->assertTrue(Hash::check($codes[0], $this->user->two_factor_recovery_codes[0]));
    }

    public function testItConfirmThrowsExceptionWhenSetupNotStarted(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('two-factor.setup_not_started'));

        $this->service->confirm($this->user, '123456');
    }

    public function testItConfirmThrowsExceptionWhenCodeIsInvalid(): void
    {
        $this->service->setup($this->user);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('two-factor.invalid_code'));

        $this->service->confirm($this->user, '000000');
    }

    public function testItConfirmBlocksAfterTooManyInvalidAttempts(): void
    {
        $this->service->setup($this->user);
        RateLimiter::clear('two-factor|confirm|'.$this->user->id);

        for ($i = 0; $i < 5; $i++) {
            try {
                $this->service->confirm($this->user, '000000');
            } catch (\DomainException) {
            }
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('two-factor.too_many_attempts'));

        $this->service->confirm($this->user, '000000');
    }

    public function testItDisableSuccessfullyWithRecoveryCode(): void
    {
        $codes = $this->enableTwoFactor();

        $this->service->disable($this->user, $codes[0]);

        $this->user->refresh();
        $this->assertFalse($this->user->hasTwoFactorEnabled());
        $this->assertNull($this->user->two_factor_secret);
        $this->assertNull($this->user->two_factor_recovery_codes);
    }

    public function testItDisableThrowsExceptionWhenCodeIsInvalid(): void
    {
        $this->enableTwoFactor();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('two-factor.invalid_code'));

        $this->service->disable($this->user, '000000');
    }

    public function testItDisableThrowsExceptionWhenNotEnabled(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('two-factor.not_enabled'));

        $this->service->disable($this->user, '123456');
    }

    public function testItRegenerateRecoveryCodesInvalidatesOldOnes(): void
    {
        $old = $this->enableTwoFactor();

        $new = $this->service->regenerateRecoveryCodes($this->user, $old[0]);

        $this->user->refresh();
        $this->assertCount(8, $new);
        $this->assertNotEquals($old, $new);
        $this->assertFalse(Hash::check($old[1], $this->user->two_factor_recovery_codes[0]));
        $this->assertTrue(Hash::check($new[0], $this->user->two_factor_recovery_codes[0]));
    }

    public function testItFinishChallengeWithRecoveryCodeConsumesIt(): void
    {
        $codes = $this->enableTwoFactor();
        $this->service->startChallenge($this->user, false);

        $user = $this->service->finishChallenge($codes[0]);

        $this->user->refresh();
        $this->assertSame($this->user->id, $user->id);
        $this->assertCount(7, $this->user->two_factor_recovery_codes);

        $this->expectException(\DomainException::class);
        $this->service->finishChallenge($codes[0]);
    }

    public function testItFinishChallengeRejectsReplayOfTotpCode(): void
    {
        $this->enableTwoFactor();
        $this->service->startChallenge($this->user, false);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('two-factor.invalid_code'));

        // O código usado na confirmação já foi consumido e não pode ser reaproveitado.
        $this->service->finishChallenge($this->google2fa->getCurrentOtp($this->user->two_factor_secret));
    }

    public function testItFinishChallengeThrowsExceptionWhenChallengeExpired(): void
    {
        $this->enableTwoFactor();
        $this->service->startChallenge($this->user, false);
        session()->put(TwoFactorService::CHALLENGE_SESSION_KEY.'.expires_at', now()->subMinute()->timestamp);

        $this->expectException(\App\Exceptions\ExpiredFlowException::class);
        $this->expectExceptionMessage(__('two-factor.expired_challenge'));

        $this->service->finishChallenge('123456');
    }

    public function testItFinishChallengeThrowsExceptionWithoutChallenge(): void
    {
        $this->expectException(\App\Exceptions\ExpiredFlowException::class);
        $this->expectExceptionMessage(__('two-factor.expired_challenge'));

        $this->service->finishChallenge('123456');
    }

    public function testItSendResetLinkQueuesMailToUser(): void
    {
        Mail::fake();
        $this->enableTwoFactor();
        $this->service->startChallenge($this->user, false);

        $this->service->sendResetLink();

        Mail::assertSent(TwoFactorResetMail::class, fn ($mail) => $mail->hasTo($this->user->email));
    }

    public function testItResetByLinkDisablesTwoFactorOnlyOnce(): void
    {
        Mail::fake();
        $this->enableTwoFactor();
        $this->service->startChallenge($this->user, false);
        $this->service->sendResetLink();

        $url = null;
        Mail::assertSent(TwoFactorResetMail::class, function ($mail) use (&$url) {
            $url = $mail->url;

            return true;
        });
        preg_match('#/reset/(\d+)/([a-f0-9]+)#', $url, $matches);

        $this->service->resetByLink((int) $matches[1], $matches[2]);

        $this->assertFalse($this->user->fresh()->hasTwoFactorEnabled());

        $this->expectException(\DomainException::class);
        $this->service->resetByLink((int) $matches[1], $matches[2]);
    }

    public function testItResetByLinkThrowsExceptionWhenTokenIsInvalid(): void
    {
        $this->enableTwoFactor();

        $this->expectException(\App\Exceptions\ExpiredFlowException::class);
        $this->expectExceptionMessage(__('two-factor.reset_link_invalid'));

        $this->service->resetByLink($this->user->id, 'invalid-token');
    }
}
