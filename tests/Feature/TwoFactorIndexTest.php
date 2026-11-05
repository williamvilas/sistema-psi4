<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorIndexTest extends TestCase
{
    use DatabaseTransactions;

    public function testItShowsActivationWizardWhenTwoFactorIsDisabled(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        $response = $this->actingAs($user)->get('/two-factor');

        $response->assertOk()
            ->assertSee('id="tf-wizard"', false)
            ->assertDontSee('id="tf-manage"', false)
            ->assertSee('Inativa')
            ->assertSee('two-factor-banner', false);
    }

    public function testItShowsManagementWhenTwoFactorIsEnabled(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);
        $user->savePendingTwoFactorSecret((new Google2FA())->generateSecretKey(32));
        $user->enableTwoFactor(['hash-1', 'hash-2', 'hash-3'], 0);

        $response = $this->actingAs($user)->get('/two-factor');

        $response->assertOk()
            ->assertSee('id="tf-manage"', false)
            ->assertDontSee('id="tf-wizard"', false)
            ->assertSee('3 restantes')
            ->assertSee('Configurado em '.now()->format('d/m/Y'))
            ->assertDontSee('two-factor-banner', false);
    }
}
