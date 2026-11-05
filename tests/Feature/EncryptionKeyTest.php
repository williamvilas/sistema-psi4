<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AccessLogs;
use App\Models\User;
use App\Models\UserEncryptionKeys;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EncryptionKeyTest extends TestCase
{
    use DatabaseTransactions;

    private const KEY_CHECK = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private function payload(array $overrides = []): array
    {
        return [
            'passphrase_wrapped_key' => str_repeat('A', 80),
            'passphrase_salt' => str_repeat('B', 22).'==',
            'passphrase_iterations' => 600000,
            'recovery_wrapped_key' => str_repeat('C', 80),
            'recovery_salt' => str_repeat('D', 22).'==',
            'recovery_iterations' => 600000,
            'key_check' => self::KEY_CHECK,
            ...$overrides,
        ];
    }

    private function userWithKey(): User
    {
        $user = User::factory()->create(['current_plan' => 'professional']);
        $this->actingAs($user)->postJson(route('encryption-key.store'), $this->payload())->assertOk();

        return $user;
    }

    public function testItShowsSetupWizardWhenKeyDoesNotExist(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        $html = $this->actingAs($user)->get(route('encryption-key.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="form-store-encryption-key"', $html);
        $this->assertStringNotContainsString('id="form-update-passphrase"', $html);
    }

    public function testItShowsManagementWhenKeyExists(): void
    {
        $user = $this->userWithKey();

        $html = $this->actingAs($user)->get(route('encryption-key.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="form-update-passphrase"', $html);
        $this->assertStringContainsString('id="form-recover-encryption-key"', $html);
        $this->assertStringNotContainsString('id="form-store-encryption-key"', $html);
    }

    public function testItIgnoresExternalRedirect(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        $html = $this->actingAs($user)->get(route('encryption-key.index', ['redirect' => 'https://evil.test']))->getContent();

        $this->assertStringContainsString('data-redirect=""', $html);
    }

    public function testItStoreSavesWrapsHashedCheckAndAccessLog(): void
    {
        $user = $this->userWithKey();

        $key = UserEncryptionKeys::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(hash('sha256', self::KEY_CHECK), $key->key_check);
        $this->assertSame(str_repeat('C', 80), $key->recovery_wrapped_key);
        $this->assertTrue(AccessLogs::query()->where('user_id', $user->id)->where('action', 'encryption_key_created')->exists());
    }

    public function testItStoreRejectsSecondKey(): void
    {
        $user = $this->userWithKey();

        $this->actingAs($user)->postJson(route('encryption-key.store'), $this->payload())
            ->assertStatus(400)
            ->assertJson(['message' => __('encryption-key.already_exists')]);
    }

    public function testItStoreRejectsWeakIterations(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        $this->actingAs($user)->postJson(route('encryption-key.store'), $this->payload(['passphrase_iterations' => 1000]))
            ->assertStatus(422);

        $this->assertFalse(UserEncryptionKeys::query()->where('user_id', $user->id)->exists());
    }

    public function testItDetailsNeverReturnsKeyCheck(): void
    {
        $user = $this->userWithKey();

        $response = $this->actingAs($user)->getJson(route('encryption-key.show'))->assertOk();

        $response->assertJsonPath('passphrase_wrapped_key', str_repeat('A', 80));
        $response->assertJsonMissingPath('key_check');
        $response->assertJsonMissingPath('user_id');
    }

    public function testItDetailsFailsWhenKeyDoesNotExist(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        $this->actingAs($user)->getJson(route('encryption-key.show'))->assertStatus(400);
    }

    public function testItUpdatePassphraseRequiresMatchingKeyCheck(): void
    {
        $user = $this->userWithKey();

        $this->actingAs($user)->putJson(route('encryption-key.passphrase.update'), [
            'passphrase_wrapped_key' => str_repeat('E', 80),
            'passphrase_salt' => str_repeat('F', 22).'==',
            'passphrase_iterations' => 600000,
            'key_check' => str_repeat('0', 64),
        ])->assertStatus(400);

        $this->assertSame(str_repeat('A', 80), UserEncryptionKeys::query()->where('user_id', $user->id)->value('passphrase_wrapped_key'));
    }

    public function testItUpdatePassphraseSuccessfully(): void
    {
        $user = $this->userWithKey();

        $this->actingAs($user)->putJson(route('encryption-key.passphrase.update'), [
            'passphrase_wrapped_key' => str_repeat('E', 80),
            'passphrase_salt' => str_repeat('F', 22).'==',
            'passphrase_iterations' => 600000,
            'key_check' => self::KEY_CHECK,
        ])->assertOk();

        $key = UserEncryptionKeys::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(str_repeat('E', 80), $key->passphrase_wrapped_key);
        $this->assertSame(str_repeat('C', 80), $key->recovery_wrapped_key);
    }

    public function testItCreateMedicalRecordRedirectsToSetupWithoutKey(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        $this->actingAs($user)->get(route('medical-records.create'))
            ->assertRedirect(route('encryption-key.index', ['redirect' => '/medical-records/create']));
    }

    public function testItCreateMedicalRecordOpensEditorWithKey(): void
    {
        $user = $this->userWithKey();

        $this->actingAs($user)->get(route('medical-records.create'))
            ->assertOk()
            ->assertSee('id="form-store-medical-records"', false);
    }
}
