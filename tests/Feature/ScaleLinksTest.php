<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AccessLogs;
use App\Models\Patients;
use App\Models\PatientScaleApplications;
use App\Models\PatientScaleLinks;
use App\Models\User;
use App\Models\UserEncryptionKeys;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScaleLinksTest extends TestCase
{
    use DatabaseTransactions;

    private const PUBLIC_KEY = 'MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE'.'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA==';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake('s3');
        Storage::disk('s3')->buildTemporaryUploadUrlsUsing(fn (string $path) => ['url' => 'https://s3.test/'.$path, 'headers' => []]);
    }

    private function userWithKey(bool $withScaleKey = true): User
    {
        $user = User::factory()->create(['current_plan' => 'professional', 'name' => 'Ana Psicóloga Teste']);
        UserEncryptionKeys::query()->create([
            'user_id' => $user->id,
            'passphrase_wrapped_key' => str_repeat('A', 80),
            'passphrase_salt' => base64_encode(random_bytes(16)),
            'passphrase_iterations' => 600000,
            'recovery_wrapped_key' => str_repeat('B', 80),
            'recovery_salt' => base64_encode(random_bytes(16)),
            'recovery_iterations' => 600000,
            'key_check' => hash('sha256', 'check'),
            'scale_public_key' => $withScaleKey ? self::PUBLIC_KEY : null,
            'scale_wrapped_private_key' => $withScaleKey ? str_repeat('C', 224) : null,
        ]);

        return $user;
    }

    private function patientFor(User $user): Patients
    {
        return Patients::query()->create([
            'user_id' => $user->id,
            'name' => 'Maria Paciente Sigilosa',
            'document' => '52998224725',
            'phone' => '119'.random_int(10000000, 99999999),
        ]);
    }

    private function createLink(User $user, Patients $patient, string $scale = 'phq9'): string
    {
        $response = $this->actingAs($user)->postJson(route('scale-links.store'), [
            'patient_id' => encrypt($patient->id),
            'scale' => $scale,
        ])->assertOk();

        return $response->json('url');
    }

    private function tokenFrom(string $url): string
    {
        return basename(parse_url($url, PHP_URL_PATH));
    }

    public function testItCreatesLinkAndReturnsSameLinkWhileItIsPending(): void
    {
        $user = $this->userWithKey();
        $patient = $this->patientFor($user);

        $first = $this->createLink($user, $patient);
        $second = $this->createLink($user, $patient);

        $this->assertSame($first, $second);
        $this->assertMatchesRegularExpression('#/avaliacao/[A-Za-z0-9_-]{43}$#', $first);
        $this->assertSame(1, PatientScaleLinks::query()->where('patient_id', $patient->id)->count());

        $link = PatientScaleLinks::query()->where('patient_id', $patient->id)->first();
        $this->assertSame(hash('sha256', $this->tokenFrom($first)), $link->token_hash);
        $this->assertNotSame($this->tokenFrom($first), $link->getRawOriginal('token'), 'O token fica cifrado no banco.');
    }

    public function testItAllowsOneLinkPerScaleAndPatientPerDay(): void
    {
        $user = $this->userWithKey();
        $patient = $this->patientFor($user);
        $this->createLink($user, $patient, 'phq9');
        $this->createLink($user, $patient, 'gad7');
        PatientScaleLinks::query()->where('patient_id', $patient->id)->where('scale', 'phq9')->update(['completed_at' => now()]);

        $this->actingAs($user)->postJson(route('scale-links.store'), [
            'patient_id' => encrypt($patient->id),
            'scale' => 'phq9',
        ])->assertStatus(400)->assertJson(['message' => __('scales.link_daily_limit')]);

        $this->assertSame(2, PatientScaleLinks::query()->where('patient_id', $patient->id)->count());
    }

    public function testItRequiresScaleKeyPairToCreateLink(): void
    {
        $user = $this->userWithKey(false);
        $patient = $this->patientFor($user);

        $this->actingAs($user)->postJson(route('scale-links.store'), [
            'patient_id' => encrypt($patient->id),
            'scale' => 'phq9',
        ])->assertStatus(400)->assertJson(['message' => __('scales.scale_key_required')]);
    }

    public function testItDoesNotCreateLinkForPatientOfAnotherUser(): void
    {
        $user = $this->userWithKey();
        $other = $this->patientFor($this->userWithKey());

        $this->actingAs($user)->postJson(route('scale-links.store'), [
            'patient_id' => encrypt($other->id),
            'scale' => 'phq9',
        ])->assertStatus(400);

        $this->assertSame(0, PatientScaleLinks::query()->where('patient_id', $other->id)->count());
    }

    public function testItShowsPublicPageWithoutLoginAndWithoutPersonalData(): void
    {
        $user = $this->userWithKey();
        $patient = $this->patientFor($user);
        $url = $this->createLink($user, $patient);
        auth()->logout();

        $response = $this->get($url)->assertOk();

        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $response->assertSee('Pouco interesse ou pouco prazer em fazer as coisas');
        $response->assertDontSee('Maria Paciente Sigilosa');
        $response->assertDontSee('52998224725');
        $response->assertDontSee('529.982.247-25');
        $response->assertDontSee('Ana Psicóloga Teste');
        $response->assertDontSee('CRP');
        // Faixas e orientações clínicas ficam só com a psicóloga.
        $response->assertDontSee('critical_message');
        $response->assertDontSee('ranges');
    }

    public function testItShowsNeutralPageForUnknownToken(): void
    {
        $this->get('/avaliacao/'.str_repeat('a', 43))
            ->assertNotFound()
            ->assertSee('Este link não está mais disponível');
    }

    public function testItCompletesAnswerAndClosesLink(): void
    {
        $user = $this->userWithKey();
        $patient = $this->patientFor($user);
        $token = $this->tokenFrom($this->createLink($user, $patient));
        auth()->logout();

        $upload = $this->postJson(route('public-scale.upload-url', ['token' => $token]))->assertOk();
        $this->assertStringStartsWith('https://s3.test/scale-applications/', $upload->json('link'));
        Storage::disk('s3')->put($upload->json('content_path'), 'PSE-cifrado');

        $this->postJson(route('public-scale.submit', ['token' => $token]))->assertOk()->assertJson(['done' => true]);

        $application = PatientScaleApplications::query()->where('patient_id', $patient->id)->first();
        $this->assertNotNull($application);
        $this->assertSame('ecdh', $application->encryption->value);
        $this->assertSame($upload->json('content_path'), $application->content_path);

        $link = PatientScaleLinks::query()->where('patient_id', $patient->id)->first();
        $this->assertNotNull($link->completed_at);
        $this->assertSame($application->id, $link->scale_application_id);

        $this->assertTrue(AccessLogs::query()
            ->where('user_id', $user->id)
            ->where('action', 'scale_application_answered')
            ->where('scale_application_id', $application->id)
            ->exists());

        // Depois de respondido, o link não funciona mais.
        $this->get(route('public-scale.show', ['token' => $token]))->assertNotFound();
        $this->postJson(route('public-scale.submit', ['token' => $token]))->assertStatus(410);
        $this->postJson(route('public-scale.upload-url', ['token' => $token]))->assertStatus(410);
        $this->assertSame(1, PatientScaleApplications::query()->where('patient_id', $patient->id)->count());
    }

    public function testItRejectsSubmitWithoutUploadedFile(): void
    {
        $user = $this->userWithKey();
        $patient = $this->patientFor($user);
        $token = $this->tokenFrom($this->createLink($user, $patient));

        $this->postJson(route('public-scale.upload-url', ['token' => $token]))->assertOk();

        $this->postJson(route('public-scale.submit', ['token' => $token]))
            ->assertStatus(400)
            ->assertJson(['message' => __('scales.link_upload_missing')]);

        $this->assertNull(PatientScaleLinks::query()->where('patient_id', $patient->id)->value('completed_at'));
    }

    public function testItRevokesLinkAndPublicPageStopsWorking(): void
    {
        $user = $this->userWithKey();
        $patient = $this->patientFor($user);
        $url = $this->createLink($user, $patient);
        $link = PatientScaleLinks::query()->where('patient_id', $patient->id)->first();

        $this->actingAs($user)->patchJson(route('scale-links.revoke'), ['id' => encrypt($link->id)])->assertOk();

        $this->get($url)->assertNotFound();
        // Cancelado não conta no limite do dia: dá para gerar outro.
        $this->assertNotSame($url, $this->createLink($user, $patient));
    }

    public function testItExpiresLinkAfterValidDays(): void
    {
        $user = $this->userWithKey();
        $patient = $this->patientFor($user);
        $url = $this->createLink($user, $patient);

        $this->travel(PatientScaleLinks::VALID_DAYS)->days();
        $this->travel(1)->minutes();

        $this->get($url)->assertNotFound();
    }

    public function testItPaginatesLinksOfPatient(): void
    {
        $user = $this->userWithKey();
        $patient = $this->patientFor($user);
        $this->createLink($user, $patient, 'phq9');
        $this->createLink($user, $patient, 'gad7');

        $html = $this->actingAs($user)->getJson(route('scale-links.paginate', ['patient_id' => encrypt($patient->id)]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('PHQ-9', $html);
        $this->assertStringContainsString('GAD-7', $html);
        $this->assertStringContainsString('Aguardando', $html);
        $this->assertStringContainsString('data-copy-scale-link', $html);
    }

    public function testItStoresScaleKeyPairOnlyOnce(): void
    {
        $user = $this->userWithKey(false);
        $payload = ['public_key' => self::PUBLIC_KEY, 'wrapped_private_key' => str_repeat('D', 224)];

        $this->actingAs($user)->postJson(route('encryption-key.scale-key-pair.store'), $payload)->assertOk();
        $this->actingAs($user)->postJson(route('encryption-key.scale-key-pair.store'), [...$payload, 'wrapped_private_key' => str_repeat('E', 224)])
            ->assertStatus(400)
            ->assertJson(['message' => __('scales.scale_key_already_exists')]);

        $this->assertSame(str_repeat('D', 224), UserEncryptionKeys::query()->where('user_id', $user->id)->value('scale_wrapped_private_key'));

        $this->actingAs($user)->getJson(route('encryption-key.show'))
            ->assertOk()
            ->assertJson(['scale_public_key' => self::PUBLIC_KEY, 'scale_wrapped_private_key' => str_repeat('D', 224)]);
    }
}
