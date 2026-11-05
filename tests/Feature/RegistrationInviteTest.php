<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\NotifyNewRegistrationLeadJob;
use App\Models\RegistrationInvitations;
use App\Models\RegistrationLeads;
use App\Models\User;
use App\Services\RegistrationInvitationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RegistrationInviteTest extends TestCase
{
    use DatabaseTransactions;

    private function payload(string $email): array
    {
        return [
            'name' => 'Maria Teste',
            'email' => $email,
            'password' => 'Senha-Forte-123!',
            'password_confirmation' => 'Senha-Forte-123!',
            'terms' => '1',
            'timezone' => 'America/Sao_Paulo',
        ];
    }

    private function newToken(): string
    {
        return app(RegistrationInvitationService::class)->create()['token'];
    }

    public function testItAllowsRegistrationWhenInviteOnlyIsDisabled(): void
    {
        config(['registration.invite_only' => false]);

        $this->get('/register')->assertOk();
        $this->postJson('/register', $this->payload('livre@example.com'))->assertOk();
        $this->assertDatabaseHas('users', ['email' => 'livre@example.com']);
    }

    public function testItRedirectsRegisterPageToInviteRequestWithoutInvite(): void
    {
        config(['registration.invite_only' => true]);

        $this->get('/register')->assertRedirect(route('invite.request'));
    }

    public function testItBlocksRegisterPostWithoutInvite(): void
    {
        config(['registration.invite_only' => true]);

        $this->postJson('/register', $this->payload('semconvite@example.com'))
            ->assertForbidden()
            ->assertJsonPath('link', route('invite.request'));
        $this->assertDatabaseMissing('users', ['email' => 'semconvite@example.com']);
    }

    public function testItRegistersWithValidInviteAndConsumesIt(): void
    {
        config(['registration.invite_only' => true]);
        $token = $this->newToken();

        $this->get('/register?convite='.$token)->assertRedirect(route('register'));
        $this->get('/register')->assertOk();
        $this->postJson('/register', $this->payload('comconvite@example.com'))->assertOk();

        $user = User::query()->where('email', 'comconvite@example.com')->firstOrFail();
        $this->assertDatabaseHas('registration_invitations', [
            'token_hash' => hash('sha256', $token),
            'used_by_user_id' => $user->id,
        ]);
    }

    public function testItRejectsAlreadyUsedInvite(): void
    {
        config(['registration.invite_only' => true]);
        $token = $this->newToken();
        (new RegistrationInvitations())->consumeByTokenHash(hash('sha256', $token), User::factory()->create()->id);

        $this->get('/register?convite='.$token)->assertRedirect(route('register'));
        $this->get('/register')->assertRedirect(route('invite.request'));
    }

    public function testItRejectsExpiredInvite(): void
    {
        config(['registration.invite_only' => true]);
        $token = $this->newToken();
        RegistrationInvitations::query()->update(['expires_at' => now()->subMinute()]);

        $this->get('/register?convite='.$token);
        $this->get('/register')->assertRedirect(route('invite.request'));
    }

    public function testItDoesNotConsumeInviteWhenRegistrationFails(): void
    {
        config(['registration.invite_only' => true]);
        $token = $this->newToken();
        $existing = User::factory()->create();

        $this->get('/register?convite='.$token);
        $this->postJson('/register', $this->payload($existing->email))->assertStatus(422);

        $this->assertDatabaseHas('registration_invitations', ['token_hash' => hash('sha256', $token), 'used_at' => null]);
    }

    public function testItStoresLeadWithoutDuplicatingWhatsapp(): void
    {
        config(['registration.invite_only' => true]);
        Queue::fake();
        $data = ['name' => 'ana lima', 'whatsapp' => '(12) 98884-0421', 'consent' => '1'];

        $this->postJson('/convite', $data)->assertOk();
        $this->postJson('/convite', array_merge($data, ['name' => 'Ana Lima Souza']))->assertOk();

        $this->assertSame(1, RegistrationLeads::query()->where('whatsapp', '12988840421')->count());
        $this->assertSame('Ana Lima Souza', RegistrationLeads::query()->where('whatsapp', '12988840421')->value('name'));
        Queue::assertPushed(NotifyNewRegistrationLeadJob::class, 1);
    }

    public function testItIgnoresLeadWhenHoneypotIsFilled(): void
    {
        config(['registration.invite_only' => true]);
        Queue::fake();

        $this->postJson('/convite', ['name' => 'Robo', 'whatsapp' => '(12) 98884-0422', 'consent' => '1', 'website' => 'http://spam'])
            ->assertOk();

        $this->assertDatabaseMissing('registration_leads', ['whatsapp' => '12988840422']);
    }

    public function testItRequiresConsentToStoreLead(): void
    {
        config(['registration.invite_only' => true]);

        $this->postJson('/convite', ['name' => 'Ana', 'whatsapp' => '(12) 98884-0423'])->assertStatus(422);
    }

    public function testItMarksLeadAsRegisteredAfterSignup(): void
    {
        config(['registration.invite_only' => true]);
        $lead = RegistrationLeads::query()->create(['name' => 'Lia', 'whatsapp' => '12988840424', 'consent_at' => now()]);
        $token = app(RegistrationInvitationService::class)->create($lead->id)['token'];

        $this->assertSame('invited', $lead->fresh()->status);

        $this->get('/register?convite='.$token);
        $this->postJson('/register', $this->payload('lia@example.com'))->assertOk();

        $this->assertSame('registered', $lead->fresh()->status);
    }
}
