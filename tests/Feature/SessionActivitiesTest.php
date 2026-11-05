<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccessLogActionEnum;
use App\Enums\VideoRoomRoleEnum;
use App\Enums\VideoRoomStatusEnum;
use App\Mail\SessionActivityPinLockedMail;
use App\Models\AccessLogs;
use App\Models\Agenda;
use App\Models\MedicalRecords;
use App\Models\Patients;
use App\Models\SessionActivities;
use App\Models\User;
use App\Models\UserEncryptionKeys;
use App\Models\VideoRoom;
use App\Services\CpfHasher;
use App\Services\JitsiTokenIssuer;
use App\Services\SessionAuthorizationService;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SessionActivitiesTest extends TestCase
{
    use DatabaseTransactions;

    private const string SECRET = 'segredo-do-prosody-para-testes-com-32-bytes';

    private const string PIN = '482913';

    private const string PUBLIC_KEY = 'MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE';

    private const string UUID = '2f1c7a7e-3d55-4a44-9c11-6a2b8e0d1f33';

    private User $user;

    private VideoRoom $videoRoom;

    private Agenda $agenda;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'psi_video.jitsi_base_url' => 'https://meet.teste',
            'psi_video.jitsi.app_id' => 'psi-teste',
            'psi_video.jitsi.app_secret' => self::SECRET,
            'psi_video.jitsi.sub' => 'meet.teste',
            'psi_video.jitsi.aud' => 'jitsi',
            'psi_video.cpf_pepper' => 'pepper-de-cpf-com-32-bytes-aleatorios',
            'psi_video.phone_pepper' => 'pepper-de-teste-com-32-bytes-aleatorios',
            'psi_video.activity_draft_secret' => 'segredo-das-dinamicas-com-32-bytes-ou-mais',
            'cors.allowed_origins' => ['https://meet.teste'],
            'filesystems.default' => 's3',
        ]);

        Queue::fake();
        Mail::fake();
        Storage::fake('s3');

        [$this->user, $this->videoRoom, $this->agenda] = $this->scenario();
    }

    /**
     * @return array{0: User, 1: VideoRoom, 2: Agenda}
     */
    private function scenario(bool $withKey = true, bool $withPin = true): array
    {
        $user = User::factory()->create([
            'current_plan' => 'professional',
            'session_activity_pin' => $withPin ? Hash::make(self::PIN) : null,
        ]);

        if ($withKey) {
            UserEncryptionKeys::query()->create([
                'user_id' => $user->id,
                'passphrase_wrapped_key' => str_repeat('A', 80),
                'passphrase_salt' => base64_encode(random_bytes(16)),
                'passphrase_iterations' => 600000,
                'recovery_wrapped_key' => str_repeat('B', 80),
                'recovery_salt' => base64_encode(random_bytes(16)),
                'recovery_iterations' => 600000,
                'key_check' => hash('sha256', 'check'),
                'scale_public_key' => self::PUBLIC_KEY,
                'scale_wrapped_private_key' => str_repeat('C', 224),
            ]);
        }

        $patient = $this->patientOf($user);
        $roomCode = strtolower(substr(md5((string) microtime(true).random_int(0, 99999)), 0, 12));
        $agenda = Agenda::query()->create([
            'user_id' => $user->id,
            'patient_id' => $patient->id,
            'type' => 'online',
            'scheduled_on' => now()->utc(),
            'ends_at' => now()->utc()->addHour(),
            'room_link' => 'https://meet.teste/'.$roomCode,
            'status' => 'scheduled',
        ]);
        $videoRoom = VideoRoom::query()->create([
            'room' => $roomCode,
            'session_id' => (string) $agenda->id,
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addHour(),
            'status' => VideoRoomStatusEnum::ACTIVE,
        ]);
        $hasher = app(CpfHasher::class);
        $videoRoom->participants()->create(['cpf_hmac' => $hasher->hmac('52998224725'), 'name' => 'Psicóloga', 'role' => VideoRoomRoleEnum::MODERATOR->value]);
        $videoRoom->participants()->create(['cpf_hmac' => $hasher->hmac('11144477735'), 'name' => 'Paciente', 'role' => VideoRoomRoleEnum::GUEST->value]);

        return [$user, $videoRoom->load('participants'), $agenda];
    }

    private function patientOf(User $user): Patients
    {
        return Patients::query()->create(['user_id' => $user->id, 'name' => 'Paciente Teste', 'document' => '52998224725', 'phone' => '119'.random_int(10000000, 99999999)]);
    }

    private function jwtFor(VideoRoom $videoRoom, VideoRoomRoleEnum $role = VideoRoomRoleEnum::MODERATOR): string
    {
        $participant = $videoRoom->participants->firstWhere('role', $role);

        return app(JitsiTokenIssuer::class)->issue($videoRoom, $participant)['jwt'];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function postAs(string $path, array $body, ?string $jwt = null): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.($jwt ?? $this->jwtFor($this->videoRoom)))->postJson($path, $body);
    }

    private function grant(): string
    {
        return (string) $this->postAs('/api/video-room/activities/unlock', ['pin' => self::PIN])->assertOk()->json('grant');
    }

    /**
     * @return array<string, string>
     */
    private function storePayload(string $grant, string $content = 'PSA-cifrado'): array
    {
        return ['uuid' => self::UUID, 'activity' => 'thermometer', 'content' => base64_encode($content), 'grant' => $grant];
    }

    public function testItReturnsContextWithoutSecrets(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->jwtFor($this->videoRoom))
            ->getJson('/api/video-room/activities/context')
            ->assertOk()
            ->assertJsonPath('can_save', true)
            ->assertJsonPath('public_key', self::PUBLIC_KEY)
            ->assertJsonCount(5, 'catalog');

        $this->assertArrayNotHasKey('key', $response->json('draft'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $response->json('draft.patient_ref'));
    }

    public function testItDeniesContextWithGuestToken(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->jwtFor($this->videoRoom, VideoRoomRoleEnum::GUEST))
            ->getJson('/api/video-room/activities/context')
            ->assertUnauthorized();
    }

    public function testItDeniesTokenFromRemovedModerator(): void
    {
        $jwt = $this->jwtFor($this->videoRoom);
        $this->videoRoom->participants()->where('role', VideoRoomRoleEnum::MODERATOR->value)->delete();

        $this->withHeader('Authorization', 'Bearer '.$jwt)->getJson('/api/video-room/activities/context')->assertUnauthorized();
    }

    public function testItDeniesActivityStoreWithExpiredTokenAfterGrace(): void
    {
        $grant = $this->grant();
        $jwt = $this->jwtFor($this->videoRoom);
        Carbon::setTestNow(now()->addDays(2));
        JWT::$timestamp = now()->getTimestamp();

        try {
            $this->postAs('/api/video-room/activities', $this->storePayload($grant), $jwt)->assertUnauthorized();
        } finally {
            JWT::$timestamp = null;
            Carbon::setTestNow();
        }
    }

    public function testItAcceptsExpiredTokenWithinGrace(): void
    {
        $this->videoRoom->update(['ends_at' => now()->subHours(1)]);
        $grant = $this->grant();
        $payload = (array) JWT::jsonDecode(JWT::urlsafeB64Decode(explode('.', $this->jwtFor($this->videoRoom))[1]));
        $expired = JWT::encode(['exp' => time() - 3600] + $payload, self::SECRET, 'HS256');

        $this->postAs('/api/video-room/activities', $this->storePayload($grant), $expired)->assertCreated();
    }

    public function testItDeniesActivityUnlockWithWrongPin(): void
    {
        $this->postAs('/api/video-room/activities/unlock', ['pin' => '000111'])
            ->assertForbidden()
            ->assertJsonPath('code', 'pin_invalid')
            ->assertJsonMissingPath('grant');

        $this->assertSame(1, $this->user->refresh()->session_activity_pin_attempts);
        $this->assertDatabaseHas('access_logs', ['user_id' => $this->user->id, 'action' => AccessLogActionEnum::SESSION_ACTIVITY_PIN_FAILED->value]);
    }

    public function testItBlocksActivityUnlockAfterFivePinFailures(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->user->forceFill(['session_activity_pin_attempts' => $attempt - 1])->save();
            $response = $this->postAs('/api/video-room/activities/unlock', ['pin' => '000111']);
        }

        $response->assertForbidden()->assertJsonPath('code', 'pin_locked');
        Mail::assertSent(SessionActivityPinLockedMail::class);

        // Nem o PIN certo abre depois do bloqueio.
        $this->postAs('/api/video-room/activities/unlock', ['pin' => self::PIN])->assertForbidden()->assertJsonPath('code', 'pin_locked');
    }

    public function testItDeniesActivityStoreWithGrantFromAnotherRoom(): void
    {
        $grant = $this->grant();
        [, $otherRoom] = $this->scenario();

        $this->postAs('/api/video-room/activities', $this->storePayload($grant), $this->jwtFor($otherRoom))
            ->assertForbidden()
            ->assertJsonPath('code', 'pin_invalid');
    }

    public function testItReturnsDifferentDraftScopeForAnotherRoomOrPatient(): void
    {
        $first = $this->postAs('/api/video-room/activities/unlock', ['pin' => self::PIN])->assertOk()->json('draft');

        $this->agenda->update(['patient_id' => $this->patientOf($this->user)->id]);
        $otherPatient = $this->postAs('/api/video-room/activities/unlock', ['pin' => self::PIN])->assertOk()->json('draft');

        $this->assertNotSame($first['patient_ref'], $otherPatient['patient_ref']);
        $this->assertNotSame($first['key'], $otherPatient['key']);
        $this->assertSame($first['psychologist_ref'], $otherPatient['psychologist_ref']);

        [, $otherRoom] = $this->scenario();
        $otherRoomDraft = $this->postAs('/api/video-room/activities/unlock', ['pin' => self::PIN], $this->jwtFor($otherRoom))->assertOk()->json('draft');

        $this->assertNotSame($first['key'], $otherRoomDraft['key']);
        $this->assertNotSame($first['psychologist_ref'], $otherRoomDraft['psychologist_ref']);
    }

    public function testItDoesNotReleaseDraftKeyAfterExpiration(): void
    {
        $this->videoRoom->update(['ends_at' => now()->subHours(7)]);

        $this->postAs('/api/video-room/activities/unlock', ['pin' => self::PIN])->assertForbidden()->assertJsonMissingPath('draft');
    }

    public function testItRejectsPlainTextContent(): void
    {
        $this->postAs('/api/video-room/activities', $this->storePayload($this->grant(), '{"texto":"em claro"}'))->assertUnprocessable();

        $this->assertDatabaseMissing('session_activities', ['uuid' => self::UUID]);
    }

    public function testItRejectsContentOverSizeLimit(): void
    {
        $this->postAs('/api/video-room/activities', $this->storePayload($this->grant(), 'PSA'.str_repeat('x', 70000)))->assertUnprocessable();
    }

    public function testItCreatesMedicalRecordAndConsumesPlanLimit(): void
    {
        $this->postAs('/api/video-room/activities', $this->storePayload($this->grant()))->assertCreated();

        $activity = SessionActivities::query()->where('uuid', self::UUID)->firstOrFail();
        $record = MedicalRecords::query()->findOrFail($activity->medical_record_id);

        $this->assertSame($this->agenda->patient_id, $record->patient_id);
        $this->assertSame('Termômetro emocional', $record->title);
        $this->assertSame('medical-records/sa-'.self::UUID.'.psa', $record->notes_path);
        Storage::disk('s3')->assertExists($record->notes_path);
        Storage::disk('s3')->assertExists($activity->content_path);
        $this->assertSame('PSA-cifrado', Storage::disk('s3')->get($activity->content_path));
        $this->assertDatabaseHas('user_feature_usages', ['user_id' => $this->user->id, 'feature' => 'medical_records']);
        $this->assertSame(1, AccessLogs::query()->where('user_id', $this->user->id)->where('action', AccessLogActionEnum::SESSION_ACTIVITY_CREATED->value)->count());
    }

    public function testItIsIdempotentForSameUuid(): void
    {
        $grant = $this->grant();

        $this->postAs('/api/video-room/activities', $this->storePayload($grant))->assertCreated();
        $this->postAs('/api/video-room/activities', $this->storePayload($grant))->assertOk();

        $this->assertSame(1, SessionActivities::query()->where('uuid', self::UUID)->count());
    }

    public function testItDoesNotReuseUuidFromAnotherPsychologist(): void
    {
        $this->postAs('/api/video-room/activities', $this->storePayload($this->grant()))->assertCreated();

        [$otherUser, $otherRoom] = $this->scenario();
        $jwt = $this->jwtFor($otherRoom);
        $grant = $this->postAs('/api/video-room/activities/unlock', ['pin' => self::PIN], $jwt)->assertOk()->json('grant');

        $this->postAs('/api/video-room/activities', $this->storePayload($grant), $jwt)->assertForbidden();
        $this->assertDatabaseMissing('medical_records', ['user_id' => $otherUser->id, 'title' => 'Termômetro emocional']);
    }

    public function testItResolvesPatientFromAgendaIgnoringClientData(): void
    {
        $other = $this->patientOf($this->user);

        $this->postAs('/api/video-room/activities', [...$this->storePayload($this->grant()), 'patient_id' => $other->id])->assertCreated();

        $this->assertDatabaseHas('session_activities', ['uuid' => self::UUID, 'patient_id' => $this->agenda->patient_id]);
    }

    public function testItLinksActivityWithoutSettingAgendaOnRecord(): void
    {
        $this->postAs('/api/video-room/activities', $this->storePayload($this->grant()))->assertCreated();

        $activity = SessionActivities::query()->where('uuid', self::UUID)->firstOrFail();

        $this->assertSame($this->agenda->id, $activity->agenda_id);
        $this->assertNull(MedicalRecords::query()->findOrFail($activity->medical_record_id)->agenda_id);
    }

    public function testItReturnsCannotSaveWithoutKeyPairPinOrAgenda(): void
    {
        [, $noKey] = $this->scenario(withKey: false);
        $this->withHeader('Authorization', 'Bearer '.$this->jwtFor($noKey))->getJson('/api/video-room/activities/context')
            ->assertOk()->assertJsonPath('can_save', false)->assertJsonPath('reason', 'not_activated')->assertJsonPath('draft', null);

        [, $noPin] = $this->scenario(withPin: false);
        $this->withHeader('Authorization', 'Bearer '.$this->jwtFor($noPin))->getJson('/api/video-room/activities/context')
            ->assertOk()->assertJsonPath('can_save', true)->assertJsonPath('reason', null);

        // session_id livre que coincide com a consulta de outra pessoa não dá acesso a ela.
        [, $collision] = $this->scenario();
        $collision->update(['session_id' => (string) $this->agenda->id]);
        $this->withHeader('Authorization', 'Bearer '.$this->jwtFor($collision))->getJson('/api/video-room/activities/context')
            ->assertOk()->assertJsonPath('can_save', false)->assertJsonPath('reason', 'no_agenda');
    }

    public function testItKeepsContentOutWhenPlanLimitIsReached(): void
    {
        $grant = $this->grant();
        config(['plans.plans.professional.total_medical_records' => 0]);

        $this->postAs('/api/video-room/activities', $this->storePayload($grant))->assertForbidden()->assertJsonPath('code', 'plan_limit');

        $this->assertDatabaseMissing('session_activities', ['uuid' => self::UUID]);
        Storage::disk('s3')->assertMissing('medical-records/sa-'.self::UUID.'.psa');
        Storage::disk('s3')->assertMissing('session-activities/'.self::UUID.'.psa');
    }

    public function testItKeepsActivityWhenAgendaIsPurged(): void
    {
        $this->postAs('/api/video-room/activities', $this->storePayload($this->grant()))->assertCreated();

        $this->agenda->forceDelete();

        $activity = SessionActivities::query()->where('uuid', self::UUID)->firstOrFail();
        $this->assertNull($activity->agenda_id);
        $this->assertNotNull(MedicalRecords::query()->find($activity->medical_record_id));
    }

    public function testItDeletesBothFilesWhenRecordIsPurged(): void
    {
        $this->postAs('/api/video-room/activities', $this->storePayload($this->grant()))->assertCreated();
        $activity = SessionActivities::query()->where('uuid', self::UUID)->firstOrFail();
        $record = MedicalRecords::query()->findOrFail($activity->medical_record_id);
        $record->delete();
        $record->newQuery()->withTrashed()->whereKey($record->id)->update(['deleted_at' => now()->subYears(10)]);

        Artisan::call('app:delete-medical-records-command');

        $this->assertDatabaseMissing('session_activities', ['uuid' => self::UUID]);
        Storage::disk('s3')->assertMissing('medical-records/sa-'.self::UUID.'.psa');
        Storage::disk('s3')->assertMissing('session-activities/'.self::UUID.'.psa');
    }

    public function testItAllowsCorsOnlyFromJitsiOrigin(): void
    {
        $preflight = fn (string $origin) => $this->call('OPTIONS', '/api/video-room/activities', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
        ]);

        $preflight('https://meet.teste')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://meet.teste');

        $this->assertNotSame('https://outro.teste', $preflight('https://outro.teste')->headers->get('Access-Control-Allow-Origin'));
    }

    public function testItUpdatesPinOnlyWithKeyPairAndClearsLock(): void
    {
        $this->user->forceFill(['session_activity_pin_locked_at' => now(), 'session_activity_pin_attempts' => 5])->save();

        $this->actingAs($this->user)->putJson('/session-activities/pin', ['pin' => '739201', 'pin_confirmation' => '739201'])->assertOk();

        $this->user->refresh();
        $this->assertNull($this->user->session_activity_pin_locked_at);
        $this->assertSame(0, $this->user->session_activity_pin_attempts);
        $this->assertTrue(Hash::check('739201', $this->user->session_activity_pin));

        [$noKeyUser] = $this->scenario(withKey: false, withPin: false);
        $this->actingAs($noKeyUser)->putJson('/session-activities/pin', ['pin' => '739201', 'pin_confirmation' => '739201'])->assertStatus(400);
    }

    public function testItRejectsWeakPin(): void
    {
        $this->actingAs($this->user)->putJson('/session-activities/pin', ['pin' => '111111', 'pin_confirmation' => '111111'])->assertUnprocessable();
        $this->actingAs($this->user)->putJson('/session-activities/pin', ['pin' => '123456', 'pin_confirmation' => '123456'])->assertUnprocessable();
    }

    /**
     * @return array{id: string, url: string, public: string, info: string, private: \OpenSSLAsymmetricKey}
     */
    private function authorizationRequest(): array
    {
        $private = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $pem = openssl_pkey_get_details($private)['key'];

        UserEncryptionKeys::query()->where('user_id', $this->user->id)->update([
            'scale_public_key' => str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n"], '', $pem),
        ]);

        $request = $this->postAs('/api/video-room/activities/authorizations', [])->assertCreated()->json();
        $page = app(SessionAuthorizationService::class)->pendingFor($request['id'], $this->user->id);

        return ['id' => $request['id'], 'url' => $request['url'], 'public' => $page['server_public_key'], 'info' => $page['info'], 'private' => $private];
    }

    /**
     * O mesmo cálculo de proveScalePrivateKey (crypto.js), com a chave privada dela.
     *
     * @param  array{public: string, info: string, private: \OpenSSLAsymmetricKey}  $request
     */
    private function authorizationProof(array $request): string
    {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').base64_decode($request['public']);
        $server = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n").'-----END PUBLIC KEY-----');

        return base64_encode(hash_hmac('sha256', $request['info'], openssl_pkey_derive($server, $request['private'], 32), true));
    }

    public function testItAuthorizesOnceAfterProofOfMasterPassword(): void
    {
        $request = $this->authorizationRequest();
        $result = '/api/video-room/activities/authorizations/'.$request['id'];

        $this->withHeader('Authorization', 'Bearer '.$this->jwtFor($this->videoRoom))->getJson($result)
            ->assertOk()->assertExactJson(['status' => 'pending']);

        $this->actingAs($this->user)->get('/video-room-authorizations/'.$request['id'])->assertOk()->assertSee(Patients::query()->findOrFail($this->agenda->patient_id)->name);
        $this->actingAs($this->user)->postJson('/video-room-authorizations/'.$request['id'], ['proof' => $this->authorizationProof($request)])->assertOk();
        $this->assertDatabaseHas('access_logs', ['user_id' => $this->user->id, 'action' => AccessLogActionEnum::SESSION_ACTIVITY_AUTHORIZED->value]);

        $approved = $this->withHeader('Authorization', 'Bearer '.$this->jwtFor($this->videoRoom))->getJson($result)
            ->assertOk()->assertJsonPath('status', 'approved')->json();

        $this->postAs('/api/video-room/activities', $this->storePayload($approved['grant']))->assertCreated();
        $this->withHeader('Authorization', 'Bearer '.$this->jwtFor($this->videoRoom))->getJson($result)->assertForbidden();
    }

    public function testItDeniesAuthorizationWithInvalidProof(): void
    {
        $request = $this->authorizationRequest();

        $this->actingAs($this->user)->postJson('/video-room-authorizations/'.$request['id'], ['proof' => base64_encode(str_repeat('x', 32))])->assertStatus(400);

        $this->withHeader('Authorization', 'Bearer '.$this->jwtFor($this->videoRoom))
            ->getJson('/api/video-room/activities/authorizations/'.$request['id'])
            ->assertOk()->assertExactJson(['status' => 'pending']);
    }

    public function testItDeniesAuthorizationFromAnotherPsychologist(): void
    {
        $request = $this->authorizationRequest();
        [$other] = $this->scenario();

        $this->actingAs($other)->get('/video-room-authorizations/'.$request['id'])->assertOk()->assertSee('Pedido indisponível')->assertDontSee(Patients::query()->findOrFail($this->agenda->patient_id)->name);
        $this->actingAs($other)->postJson('/video-room-authorizations/'.$request['id'], ['proof' => $this->authorizationProof($request)])->assertStatus(400);
    }

    public function testItDeniesAuthorizationResultInAnotherRoomAndAfterExpiry(): void
    {
        $request = $this->authorizationRequest();
        [, $otherRoom] = $this->scenario();

        $this->withHeader('Authorization', 'Bearer '.$this->jwtFor($otherRoom))
            ->getJson('/api/video-room/activities/authorizations/'.$request['id'])->assertForbidden();

        $jwt = $this->jwtFor($this->videoRoom);
        $this->travel(6)->minutes();

        $this->withHeader('Authorization', 'Bearer '.$jwt)
            ->getJson('/api/video-room/activities/authorizations/'.$request['id'])->assertForbidden();
    }

    public function testItDeniesAuthorizationRequestFromGuest(): void
    {
        $this->assertSame(401, $this->withHeader('Authorization', 'Bearer '.$this->jwtFor($this->videoRoom, VideoRoomRoleEnum::GUEST))->postJson('/api/video-room/activities/authorizations')->status());
    }

    public function testItRequiresLoginToApproveAuthorization(): void
    {
        $request = $this->authorizationRequest();

        $this->get('/video-room-authorizations/'.$request['id'])->assertRedirect();
    }

    public function testItDeniesThermometerFromAnotherUser(): void
    {
        $this->postAs('/api/video-room/activities', $this->storePayload($this->grant()))->assertCreated();
        [$otherUser] = $this->scenario();

        $this->actingAs($otherUser)->postJson('/session-activities/thermometer', ['patient_id' => encrypt($this->agenda->patient_id)])->assertStatus(400);
    }

    public function testItRegistersAccessLogOnThermometerList(): void
    {
        $this->postAs('/api/video-room/activities', $this->storePayload($this->grant()))->assertCreated();

        $this->actingAs($this->user)->postJson('/session-activities/thermometer', ['patient_id' => encrypt($this->agenda->patient_id)])
            ->assertOk()
            ->assertJsonCount(1, 'activities')
            ->assertJsonPath('activities.0.uuid', self::UUID);

        $this->assertDatabaseHas('access_logs', ['user_id' => $this->user->id, 'action' => AccessLogActionEnum::SESSION_ACTIVITY_VIEWED->value]);
    }
}
