<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PasswordResetService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PasswordResetService(new User());
        $this->user = User::factory()->create();
    }

    public function testItSendResetLinkSuccessfully(): void
    {
        Mail::fake();

        $this->service->sendResetLink($this->user->email);

        Mail::assertQueued(PasswordResetMail::class, fn ($mail) => $mail->hasTo($this->user->email));
    }

    public function testItThrowExceptionWhenEmailNotFound(): void
    {
        Mail::fake();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('password-reset.user_not_found'));

        try {
            $this->service->sendResetLink('naoexiste@example.com');
        } finally {
            Mail::assertNothingQueued();
        }
    }

    public function testItThrowExceptionWhenResetLinkIsThrottled(): void
    {
        Mail::fake();
        $this->service->sendResetLink($this->user->email);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('password-reset.throttled'));

        $this->service->sendResetLink($this->user->email);
    }

    public function testItResetPasswordSuccessfully(): void
    {
        Event::fake([PasswordReset::class]);
        $token = Password::createToken($this->user);

        $this->service->reset([
            'email' => $this->user->email,
            'token' => $token,
            'password' => 'NovaSenha@123',
            'password_confirmation' => 'NovaSenha@123',
        ]);

        $this->user->refresh();
        $this->assertTrue(Hash::check('NovaSenha@123', $this->user->password));
        Event::assertDispatched(PasswordReset::class);
    }

    public function testItResetPasswordClearsForcedChange(): void
    {
        $this->user->forceFill(['force_change_password' => 1, 'temporary_password_expiration' => now()])->save();
        $token = Password::createToken($this->user);

        $this->service->reset([
            'email' => $this->user->email,
            'token' => $token,
            'password' => 'NovaSenha@123',
            'password_confirmation' => 'NovaSenha@123',
        ]);

        $this->user->refresh();
        $this->assertEquals(0, $this->user->force_change_password);
        $this->assertNull($this->user->temporary_password_expiration);
    }

    public function testItThrowExceptionWhenTokenIsInvalid(): void
    {
        $oldPassword = $this->user->password;
        $this->expectException(\App\Exceptions\ExpiredFlowException::class);
        $this->expectExceptionMessage(__('password-reset.invalid_token'));

        try {
            $this->service->reset([
                'email' => $this->user->email,
                'token' => 'token-invalido',
                'password' => 'NovaSenha@123',
                'password_confirmation' => 'NovaSenha@123',
            ]);
        } finally {
            $this->assertSame($oldPassword, $this->user->fresh()->password);
        }
    }
}
