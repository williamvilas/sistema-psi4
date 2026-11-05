<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\RegistrationInvitations;
use App\Models\RegistrationLeads;
use App\Models\User;
use App\Services\RegistrationInvitationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RegistrationInvitationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private RegistrationInvitationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RegistrationInvitationService(new RegistrationInvitations(), new RegistrationLeads());
    }

    public function testItCreateStoresOnlyTheTokenHash(): void
    {
        $created = $this->service->create(source: 'supervisora');

        $this->assertSame(hash('sha256', $created['token']), $created['invitation']->token_hash);
        $this->assertSame('supervisora', $created['invitation']->source);
        $this->assertTrue($created['invitation']->expires_at->isFuture());
    }

    public function testItConsumeForUserDoNothingWhenInviteOnlyIsDisabled(): void
    {
        config(['registration.invite_only' => false]);

        $this->service->consumeForUser(User::factory()->create());

        $this->assertTrue(true);
    }

    public function testItConsumeForUserThrowsRequiredWithoutInviteInSession(): void
    {
        config(['registration.invite_only' => true]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('auth.invite_required'));

        $this->service->consumeForUser(User::factory()->create());
    }

    public function testItConsumeForUserThrowsInvalidWhenInviteWasAlreadyUsed(): void
    {
        config(['registration.invite_only' => true]);
        $token = $this->service->create()['token'];
        $this->service->rememberFromToken($token);
        $this->service->consumeForUser(User::factory()->create());
        session()->put(config('registration.session_key'), $token);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('auth.invite_invalid'));

        $this->service->consumeForUser(User::factory()->create());
    }

    public function testItRememberFromTokenReturnsFalseForUnknownToken(): void
    {
        $this->assertFalse($this->service->rememberFromToken('inexistente'));
        $this->assertFalse($this->service->hasValidInSession());
    }
}
