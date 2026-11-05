<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\RegistrationInvitations;
use App\Models\RegistrationLeads;
use App\Models\User;
use App\Services\RegistrationInvitationService;
use App\Services\SocialAuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class SocialAuthServiceTest extends TestCase
{
    use DatabaseTransactions;

    private SocialAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SocialAuthService(new User(), new RegistrationInvitationService(new RegistrationInvitations(), new RegistrationLeads()));
    }

    private function socialUser(string $id, string $email, bool $verified = true): SocialiteUser
    {
        $user = new SocialiteUser();
        $user->id = $id;
        $user->name = 'Maria Teste';
        $user->email = $email;
        $user->avatar = null;
        $user->user = ['email_verified' => $verified];

        return $user;
    }

    public function testItCreateUserWhenEmailDoesNotExist(): void
    {
        $email = 'novo-google-'.uniqid().'@example.com';

        $user = $this->service->resolveUser('google', $this->socialUser('g-1', $email));

        $this->assertTrue($user->wasRecentlyCreated);
        $this->assertSame('g-1', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('users', ['email' => $email, 'google_id' => 'g-1']);
    }

    public function testItLinkGoogleIdWhenEmailAlreadyExists(): void
    {
        $existing = User::factory()->create(['google_id' => null]);

        $user = $this->service->resolveUser('google', $this->socialUser('g-2', $existing->email));

        $this->assertSame($existing->id, $user->id);
        $this->assertSame('g-2', $user->fresh()->google_id);
    }

    public function testItReturnUserByGoogleId(): void
    {
        $existing = User::factory()->create(['google_id' => 'g-3']);

        $user = $this->service->resolveUser('google', $this->socialUser('g-3', 'outro-'.uniqid().'@example.com'));

        $this->assertSame($existing->id, $user->id);
    }

    public function testItThrowExceptionWhenEmailIsNotVerified(): void
    {
        $this->expectException(\DomainException::class);

        $this->service->resolveUser('google', $this->socialUser('g-4', 'x-'.uniqid().'@example.com', false));
    }

    public function testItCreateUserWithLinkedinId(): void
    {
        $email = 'novo-linkedin-'.uniqid().'@example.com';

        $user = $this->service->resolveUser('linkedin', $this->socialUser('l-1', $email));

        $this->assertTrue($user->wasRecentlyCreated);
        $this->assertSame('l-1', $user->linkedin_id);
        $this->assertNull($user->google_id);
        $this->assertDatabaseHas('users', ['email' => $email, 'linkedin_id' => 'l-1']);
    }

    public function testItLinkLinkedinIdWhenEmailAlreadyExists(): void
    {
        $existing = User::factory()->create(['google_id' => 'g-5', 'linkedin_id' => null]);

        $user = $this->service->resolveUser('linkedin', $this->socialUser('l-2', $existing->email));

        $fresh = $user->fresh();
        $this->assertSame($existing->id, $user->id);
        $this->assertSame('l-2', $fresh->linkedin_id);
        $this->assertSame('g-5', $fresh->google_id);
    }

    public function testItReturnUserByLinkedinId(): void
    {
        $existing = User::factory()->create(['linkedin_id' => 'l-3']);

        $user = $this->service->resolveUser('linkedin', $this->socialUser('l-3', 'outro-'.uniqid().'@example.com'));

        $this->assertSame($existing->id, $user->id);
    }

    public function testItThrowExceptionWhenLinkedinEmailIsNotVerified(): void
    {
        $this->expectException(\DomainException::class);

        $this->service->resolveUser('linkedin', $this->socialUser('l-4', 'y-'.uniqid().'@example.com', false));
    }

    public function testItThrowExceptionWhenLinkedinEmailIsMissing(): void
    {
        $this->expectException(\DomainException::class);

        $this->service->resolveUser('linkedin', $this->socialUser('l-5', ''));
    }

    public function testItReturnDriverByProvider(): void
    {
        $this->assertSame('google', $this->service->driver('google'));
        $this->assertSame('linkedin-openid', $this->service->driver('linkedin'));
    }

    public function testItThrowExceptionWhenProviderIsUnknown(): void
    {
        $this->expectException(\DomainException::class);

        $this->service->driver('facebook');
    }

    public function testItBlockNewUserWithoutInviteWhenInviteOnly(): void
    {
        config(['registration.invite_only' => true]);
        $email = 'sem-convite-'.uniqid().'@example.com';

        try {
            $this->service->resolveUser('google', $this->socialUser('g-10', $email));
            $this->fail('Deveria exigir convite.');
        } catch (\DomainException $d) {
            $this->assertSame(__('auth.invite_required'), $d->getMessage());
        }

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function testItCreateNewUserWithValidInviteWhenInviteOnly(): void
    {
        config(['registration.invite_only' => true]);
        $token = app(RegistrationInvitationService::class)->create()['token'];
        session()->put(config('registration.session_key'), $token);
        $email = 'com-convite-'.uniqid().'@example.com';

        $user = $this->service->resolveUser('google', $this->socialUser('g-11', $email));

        $this->assertTrue($user->wasRecentlyCreated);
        $this->assertDatabaseHas('registration_invitations', ['token_hash' => hash('sha256', $token), 'used_by_user_id' => $user->id]);
    }

    public function testItLetExistingUserLoginWithoutInviteWhenInviteOnly(): void
    {
        config(['registration.invite_only' => true]);
        $existing = User::factory()->create(['google_id' => 'g-12']);

        $user = $this->service->resolveUser('google', $this->socialUser('g-12', $existing->email));

        $this->assertSame($existing->id, $user->id);
    }
}
