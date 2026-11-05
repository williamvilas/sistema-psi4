<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use DatabaseTransactions;

    public function testItShowsProfileWithLockedDocumentAndSecurityShortcuts(): void
    {
        $user = User::factory()->create(['name' => 'Maria Souza', 'document' => '52998224725', 'whatsapp' => '11999990000', 'current_plan' => 'professional']);

        $html = $this->actingAs($user)->get(route('profile.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('id="form-profile-user"', $html);
        $this->assertStringContainsString('529.982.247-25', $html);
        $this->assertStringContainsString('O CPF não pode ser alterado.', $html);
        $this->assertStringContainsString(route('profile.change-password'), $html);
        $this->assertStringContainsString(route('two-factor.index'), $html);
    }

    public function testItUpdateProfileReturnsNewNames(): void
    {
        $user = User::factory()->create(['document' => null]);

        $this->actingAs($user)->putJson(route('profile.update'), [
            'name' => 'joão da silva',
            'document' => '529.982.247-25',
            'whatsapp' => '(11) 98888-7777',
            'email' => $user->email,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'João')
            ->assertJsonPath('data.full_name', 'João da Silva');

        $this->assertSame('52998224725', $user->refresh()->document);
    }

    public function testItUpdatePasswordWithCurrentPassword(): void
    {
        $user = User::factory()->create(['password' => Hash::make('senha-antiga-123')]);

        $this->actingAs($user)->putJson(route('password.update'), [
            'current_password' => 'senha-antiga-123',
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ])
            ->assertOk()
            ->assertJsonPath('message', __('profile.password_updated'));

        $this->assertTrue(Hash::check('senha-nova-456', $user->refresh()->password));
    }

    public function testItRejectPasswordUpdateWhenCurrentPasswordIsWrong(): void
    {
        $user = User::factory()->create(['password' => Hash::make('senha-antiga-123')]);

        $this->actingAs($user)->putJson(route('password.update'), [
            'current_password' => 'errada',
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('senha-antiga-123', $user->refresh()->password));
    }

    public function testItForceChangePasswordClearsFlagAndRedirectsToDashboard(): void
    {
        $user = User::factory()->create(['force_change_password' => 1]);

        $this->actingAs($user)->get(route('profile.force-change-password'))
            ->assertOk()
            ->assertSee('id="form-force-change-password"', false);

        // O middleware ForceChangePassword só libera requisições AJAX (o jQuery envia este header).
        $this->actingAs($user)->withHeader('X-Requested-With', 'XMLHttpRequest')->putJson(route('password.forceChangePassword'), [
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ])->assertOk()->assertJsonPath('url', route('dashboard'));

        $this->assertEmpty($user->refresh()->force_change_password);
    }
}
