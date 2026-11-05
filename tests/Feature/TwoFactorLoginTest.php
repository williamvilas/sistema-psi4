<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorLoginTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUserWithTwoFactor(): User
    {
        $user = User::factory()->create();
        $user->savePendingTwoFactorSecret((new Google2FA())->generateSecretKey(32));
        $user->enableTwoFactor([], 0);

        return $user;
    }

    public function testItLoginWithoutTwoFactorAuthenticatesDirectly(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertOk()->assertJsonMissing(['two_factor' => true]);
        $this->assertAuthenticatedAs($user);
    }

    public function testItLoginWithTwoFactorDoesNotAuthenticateBeforeChallenge(): void
    {
        $user = $this->makeUserWithTwoFactor();

        $response = $this->postJson('/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertOk()->assertJson(['two_factor' => true, 'link' => route('two-factor.challenge', absolute: false)]);
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect();
    }

    public function testItChallengeWithValidCodeAuthenticatesUser(): void
    {
        $user = $this->makeUserWithTwoFactor();
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password']);

        $code = (new Google2FA())->getCurrentOtp($user->two_factor_secret);
        $this->postJson('/two-factor/challenge', ['code' => $code])->assertOk()->assertJsonStructure(['link']);

        $this->assertAuthenticatedAs($user);
    }

    public function testItChallengeWithInvalidCodeKeepsUserGuest(): void
    {
        $user = $this->makeUserWithTwoFactor();
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password']);

        $this->postJson('/two-factor/challenge', ['code' => '000000'])->assertStatus(400);

        $this->assertGuest();
    }

    public function testItChallengeWithoutPendingLoginIsRejected(): void
    {
        // Fluxo expirado responde 410 com o link de volta ao login (ExpiredFlowException).
        $this->postJson('/two-factor/challenge', ['code' => '123456'])
            ->assertStatus(410)
            ->assertJsonPath('message', __('two-factor.expired_challenge'))
            ->assertJsonPath('link', route('login', ['code' => 'session-expired'], false));

        $this->assertGuest();
        $this->get('/two-factor/challenge')->assertRedirect(route('login'));
    }

    public function testItResetLinkRouteRequiresValidSignature(): void
    {
        $this->get('/two-factor/reset/1/abc')->assertStatus(403);
    }
}
