<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LegalTermsTest extends TestCase
{
    use DatabaseTransactions;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria Teste',
            'email' => 'maria.terms@example.com',
            'password' => 'Senha@12345',
            'password_confirmation' => 'Senha@12345',
            'timezone' => 'America/Sao_Paulo',
            'terms' => '1',
        ], $overrides);
    }

    public function testItShowsLegalPages(): void
    {
        $this->get(route('terms-service'))->assertOk()->assertSee('Termos de Uso');
        $this->get(route('privacy-policy'))->assertOk()->assertSee('Política de Privacidade');
    }

    public function testItLandingPageLinksToLegalPages(): void
    {
        $this->get(route('page-main'))
            ->assertOk()
            ->assertSee(route('terms-service'), false)
            ->assertSee(route('privacy-policy'), false);
    }

    public function testItRegisterStoresTermsAcceptance(): void
    {
        $this->postJson('/register', $this->payload())->assertOk();

        $user = User::where('email', 'maria.terms@example.com')->firstOrFail();
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame(config('info.legal.terms_version'), $user->terms_version);
    }

    public function testItRegisterFailsWithoutTermsAcceptance(): void
    {
        $this->postJson('/register', $this->payload(['terms' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('terms');

        $this->assertDatabaseMissing('users', ['email' => 'maria.terms@example.com']);
    }
}
