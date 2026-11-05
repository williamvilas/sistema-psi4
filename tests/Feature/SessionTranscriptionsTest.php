<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SessionTranscriptionConsentEnum;
use App\Enums\SessionTranscriptionStatusEnum;
use App\Enums\VideoRoomRoleEnum;
use App\Enums\VideoRoomStatusEnum;
use App\Jobs\ProcessSessionTranscriptionJob;
use App\Jobs\RegisterLog;
use App\Mail\SessionTranscriptionReminderMail;
use App\Models\AccessLogs;
use App\Models\Agenda;
use App\Models\MedicalRecords;
use App\Models\Patients;
use App\Models\SessionTranscriptions;
use App\Models\User;
use App\Models\UserEncryptionKeys;
use App\Models\UserFeatureUsages;
use App\Models\VideoRoom;
use App\Services\CpfHasher;
use App\Services\JitsiTokenIssuer;
use App\Services\SessionTranscriptionService;
use App\Wrappers\AwsTranscribeWrapper;
use App\Wrappers\SessionNoteWrapper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Tests\TestCase;

class SessionTranscriptionsTest extends TestCase
{
    use DatabaseTransactions;

    private const string SECRET = 'segredo-do-prosody-para-testes-com-32-bytes';

    private const string UUID = '7a1c7a7e-3d55-4a44-9c11-6a2b8e0d1f44';

    private const string SECOND_UUID = '8b1c7a7e-3d55-4a44-9c11-6a2b8e0d1f55';

    private const string SIGNED_URL = 'https://bucket.teste/transcription-audio/assinada';

    private const array DRAFT = [
        'evolucao' => 'Paciente relatou melhora do sono.',
        'procedimentos' => 'Escuta e psicoeducação.',
        'observacoes' => '',
    ];

    private User $user;

    private Patients $patient;

    private VideoRoom $videoRoom;

    private \OpenSSLAsymmetricKey $privateKey;

    private AwsTranscribeWrapper&MockInterface $aws;

    private SessionNoteWrapper&MockInterface $notes;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session_transcription.enabled' => true,
            'psi_video.jitsi_base_url' => 'https://meet.teste',
            'psi_video.jitsi.app_id' => 'psi-teste',
            'psi_video.jitsi.app_secret' => self::SECRET,
            'psi_video.jitsi.sub' => 'meet.teste',
            'psi_video.jitsi.aud' => 'jitsi',
            'psi_video.cpf_pepper' => 'pepper-de-cpf-com-32-bytes-aleatorios',
            'psi_video.phone_pepper' => 'pepper-de-teste-com-32-bytes-aleatorios',
            'psi_video.activity_draft_secret' => 'segredo-das-dinamicas-com-32-bytes-ou-mais',
            'filesystems.default' => 's3',
        ]);

        Queue::fake();
        Mail::fake();
        Storage::fake('s3');

        $this->aws = \Mockery::mock(AwsTranscribeWrapper::class);
        $this->aws->shouldReceive('segmentUploadUrl')->andReturn(['url' => self::SIGNED_URL, 'headers' => ['Content-Type' => 'audio/webm;codecs=opus']])->byDefault();
        $this->aws->shouldReceive('listSegments')->andReturn([
            ['key' => 'a/0000-0-120000.webm', 'sequence' => 0, 'start_ms' => 0, 'duration_ms' => 120000, 'format' => 'webm'],
            ['key' => 'a/0001-180000-60000.webm', 'sequence' => 1, 'start_ms' => 180000, 'duration_ms' => 60000, 'format' => 'webm'],
        ])->byDefault();
        $this->aws->shouldReceive('startJob')->byDefault();
        $this->aws->shouldReceive('jobStatus')->andReturn('COMPLETED')->byDefault();
        $this->aws->shouldReceive('readOutput')->andReturn($this->transcribeOutput())->byDefault();
        $this->aws->shouldReceive('removeSession')->byDefault();
        $this->app->instance(AwsTranscribeWrapper::class, $this->aws);

        $this->notes = \Mockery::mock(SessionNoteWrapper::class);
        $this->notes->shouldReceive('draft')->andReturn(self::DRAFT)->byDefault();
        $this->app->instance(SessionNoteWrapper::class, $this->notes);

        $this->user = User::factory()->create(['current_plan' => 'professional']);
        $this->privateKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $publicPem = openssl_pkey_get_details($this->privateKey)['key'];

        UserEncryptionKeys::query()->create([
            'user_id' => $this->user->id,
            'passphrase_wrapped_key' => str_repeat('A', 80),
            'passphrase_salt' => base64_encode(random_bytes(16)),
            'passphrase_iterations' => 600000,
            'recovery_wrapped_key' => str_repeat('B', 80),
            'recovery_salt' => base64_encode(random_bytes(16)),
            'recovery_iterations' => 600000,
            'key_check' => hash('sha256', 'check'),
            'scale_public_key' => str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n"], '', $publicPem),
            'scale_wrapped_private_key' => str_repeat('C', 224),
        ]);

        $this->patient = Patients::query()->create([
            'user_id' => $this->user->id,
            'name' => 'Paciente Teste',
            'document' => '52998224725',
            'phone' => '119'.random_int(10000000, 99999999),
            'birth_date' => now()->subYears(30)->toDateString(),
        ]);

        $roomCode = strtolower(substr(md5((string) microtime(true).random_int(0, 99999)), 0, 12));
        $agenda = Agenda::query()->create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'type' => 'online',
            'scheduled_on' => now()->utc(),
            'ends_at' => now()->utc()->addHour(),
            'room_link' => 'https://meet.teste/'.$roomCode,
            'status' => 'scheduled',
        ]);
        $this->videoRoom = VideoRoom::query()->create([
            'room' => $roomCode,
            'session_id' => (string) $agenda->id,
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addHour(),
            'status' => VideoRoomStatusEnum::ACTIVE,
        ]);

        $hasher = app(CpfHasher::class);
        $this->videoRoom->participants()->create(['cpf_hmac' => $hasher->hmac('52998224725'), 'name' => 'Psicóloga', 'role' => VideoRoomRoleEnum::MODERATOR->value]);
        $this->videoRoom->participants()->create(['cpf_hmac' => $hasher->hmac('11144477735'), 'name' => 'Paciente', 'role' => VideoRoomRoleEnum::GUEST->value]);
        $this->videoRoom->load('participants');
    }

    private function jwt(VideoRoomRoleEnum $role = VideoRoomRoleEnum::MODERATOR): string
    {
        return app(JitsiTokenIssuer::class)->issue($this->videoRoom, $this->videoRoom->participants->firstWhere('role', $role))['jwt'];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function postAs(string $path, array $body = [], VideoRoomRoleEnum $role = VideoRoomRoleEnum::MODERATOR): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->jwt($role))->postJson('/api/video-room/'.$path, $body);
    }

    private function consent(string $decision = 'accepted', string $uuid = self::UUID): TestResponse
    {
        return $this->postAs('transcriptions/'.$uuid.'/consent', ['decision' => $decision], VideoRoomRoleEnum::GUEST);
    }

    private function started(): void
    {
        $this->postAs('transcriptions', ['uuid' => self::UUID])->assertCreated();
        $this->consent()->assertOk()->assertJsonPath('status', 'recording');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function segment(int $sequence = 0, array $overrides = []): TestResponse
    {
        return $this->postAs('transcriptions/'.self::UUID.'/segments', array_merge([
            'sequence' => $sequence,
            'mime' => 'audio/webm;codecs=opus',
            'bytes' => 480000,
            'start_ms' => $sequence * 120000,
            'duration_ms' => 120000,
            'guest_count' => 1,
        ], $overrides));
    }

    private function transcription(): SessionTranscriptions
    {
        return SessionTranscriptions::query()->where('uuid', self::UUID)->firstOrFail();
    }

    private function processing(): void
    {
        $this->started();
        $this->segment()->assertOk();
        $this->postAs('transcriptions/'.self::UUID.'/complete')->assertOk()->assertJsonPath('status', 'processing');
    }

    private function process(): bool
    {
        return $this->app->make(SessionTranscriptionService::class)->process($this->transcription());
    }

    private function pendingReview(): void
    {
        $this->processing();
        $this->assertTrue($this->process());
        $this->assertSame(SessionTranscriptionStatusEnum::PENDING_REVIEW, $this->transcription()->status);
    }

    /**
     * @return array<string, mixed>
     */
    private function transcribeOutput(): array
    {
        $word = fn (string $content, float $start) => ['type' => 'pronunciation', 'start_time' => (string) $start, 'end_time' => (string) ($start + 0.4), 'alternatives' => [['content' => $content]]];

        return ['results' => ['channel_labels' => ['number_of_channels' => 2, 'channels' => [
            ['channel_label' => 'ch_0', 'items' => [$word('Como', 1.0), $word('foi', 1.5), ['type' => 'punctuation', 'alternatives' => [['content' => '?']]]]],
            ['channel_label' => 'ch_1', 'items' => [$word('Dormi', 3.0), $word('melhor', 3.5)]],
        ]]]];
    }

    /**
     * Abre o rascunho como o navegador dela abriria, com a chave privada.
     *
     * @return array<string, mixed>
     */
    private function openNote(): array
    {
        $bytes = Storage::disk('s3')->get(SessionTranscriptionService::notePath(self::UUID));
        $this->assertSame('PSN', substr($bytes, 0, 3));

        $ephemeral = substr($bytes, 4, 65);
        $spki = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$ephemeral;
        $sender = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n");
        $key = hash_hkdf('sha256', openssl_pkey_derive($sender, $this->privateKey, 32), 32, 'psi4-session-note', $ephemeral);
        $rest = substr($bytes, 81);
        $plain = openssl_decrypt(substr($rest, 0, -16), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($bytes, 69, 12), substr($rest, -16), 'psi4-session-note|'.self::UUID);

        $this->assertIsString($plain);

        return json_decode($plain, true);
    }

    public function testItOffersNothingWhileDisabled(): void
    {
        config(['session_transcription.enabled' => false]);

        $this->withHeader('Authorization', 'Bearer '.$this->jwt())
            ->getJson('/api/video-room/transcriptions/context')
            ->assertOk()
            ->assertJsonPath('can_start', false)
            ->assertJsonPath('reason', 'unavailable');

        $this->postAs('transcriptions', ['uuid' => self::UUID])->assertForbidden();
    }

    public function testItReturnsContextWithoutPatientDataNorKeys(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->jwt())->getJson('/api/video-room/transcriptions/context')->assertOk();

        $response->assertJsonPath('can_start', true)->assertJsonPath('segment_seconds', 120)->assertJsonMissingPath('public_key');
        $this->assertStringNotContainsString('Paciente Teste', $response->getContent());
    }

    public function testItStartsWithoutPsi4Authorization(): void
    {
        $this->postAs('transcriptions', ['uuid' => self::UUID])->assertCreated()->assertJsonPath('uuid', self::UUID);
        $this->postAs('transcriptions', ['uuid' => self::UUID])->assertCreated();

        $this->assertSame(SessionTranscriptionStatusEnum::AWAITING_CONSENT, $this->transcription()->status);
    }

    public function testItDeniesGuestFromStartingAndModeratorFromConsenting(): void
    {
        $this->postAs('transcriptions', ['uuid' => self::UUID], VideoRoomRoleEnum::GUEST)->assertUnauthorized();

        $this->postAs('transcriptions', ['uuid' => self::UUID])->assertCreated();
        $this->postAs('transcriptions/'.self::UUID.'/consent', ['decision' => 'accepted'])->assertUnauthorized();
    }

    public function testItBlocksTranscriptionForMinorPatient(): void
    {
        $this->patient->update(['birth_date' => now()->subYears(15)->toDateString()]);

        $this->postAs('transcriptions', ['uuid' => self::UUID])
            ->assertForbidden()
            ->assertJsonPath('message', __('session-transcriptions.minor'));
    }

    public function testItRequiresAdultConfirmationWithoutBirthDate(): void
    {
        $this->patient->update(['birth_date' => null]);

        $this->postAs('transcriptions', ['uuid' => self::UUID])->assertForbidden();
        $this->postAs('transcriptions', ['uuid' => self::UUID, 'adult_confirmed' => true])->assertCreated();
        $this->assertNotNull($this->transcription()->adult_confirmed_at);
    }

    public function testItDeniesSegmentUrlWithoutConsentFromAllPresentGuests(): void
    {
        $this->postAs('transcriptions', ['uuid' => self::UUID])->assertCreated();
        $this->segment()->assertStatus(409);

        $this->consent()->assertOk();
        $this->assertSame(SessionTranscriptionStatusEnum::RECORDING, $this->transcription()->status);
        $this->assertNotNull($this->transcription()->started_at);

        $this->segment(0, ['guest_count' => 2])->assertStatus(409);
        $this->segment()->assertOk()->assertJsonPath('url', self::SIGNED_URL)->assertJsonPath('headers.Content-Type', 'audio/webm;codecs=opus');
    }

    public function testItRejectsUnknownFormatAndOversizedSegment(): void
    {
        $this->started();

        $this->segment(0, ['mime' => 'audio/wav'])->assertUnprocessable();
        $this->segment(0, ['bytes' => 3 * 1024 * 1024])->assertUnprocessable();
        $this->segment(0, ['duration_ms' => 200000])->assertUnprocessable();
        $this->assertSame(0, $this->transcription()->parts_count);
    }

    public function testItCountsSegmentsWithoutGoingBack(): void
    {
        $this->started();

        $this->segment(1)->assertOk();
        $this->segment(0)->assertOk();
        $this->segment(1)->assertOk();

        $this->assertSame(2, $this->transcription()->parts_count);
    }

    public function testItRecordsConsentVersionAndHash(): void
    {
        $this->postAs('transcriptions', ['uuid' => self::UUID])->assertCreated();

        $this->withHeader('Authorization', 'Bearer '.$this->jwt(VideoRoomRoleEnum::GUEST))
            ->getJson('/api/video-room/transcriptions/'.self::UUID.'/consent')
            ->assertOk()
            ->assertJsonPath('text', config('session_transcription.consent_text'));

        $this->consent()->assertOk();

        $consent = $this->transcription()->consents()->firstOrFail();

        $this->assertSame(SessionTranscriptionConsentEnum::ACCEPTED, $consent->decision);
        $this->assertSame(hash('sha256', config('session_transcription.consent_text')), $consent->consent_hash);
        $this->assertSame(2, $consent->consent_version);
    }

    public function testItAllowsNewRequestAfterRefusal(): void
    {
        $this->postAs('transcriptions', ['uuid' => self::UUID])->assertCreated();
        $this->consent('refused')->assertOk()->assertJsonPath('status', 'refused');
        $this->consent('accepted')->assertStatus(409);

        $this->withHeader('Authorization', 'Bearer '.$this->jwt())
            ->getJson('/api/video-room/transcriptions/context')
            ->assertOk()
            ->assertJsonPath('can_start', true);

        $this->postAs('transcriptions', ['uuid' => self::SECOND_UUID])->assertCreated();
        $this->consent('accepted', self::SECOND_UUID)->assertOk()->assertJsonPath('status', 'recording');
    }

    public function testItAllowsNewRequestAfterRevocation(): void
    {
        $this->started();
        $this->segment()->assertOk();
        $this->consent('revoked')->assertOk()->assertJsonPath('status', 'processing');

        $this->postAs('transcriptions', ['uuid' => self::SECOND_UUID])->assertCreated();
        $this->consent('accepted', self::SECOND_UUID)->assertOk()->assertJsonPath('status', 'recording');
    }

    public function testItKeepsWhatWasRecordedWhenPatientRevokes(): void
    {
        $this->started();
        $this->segment()->assertOk();

        $this->consent('revoked')->assertOk()->assertJsonPath('status', 'processing');

        Queue::assertPushed(ProcessSessionTranscriptionJob::class, fn ($job) => $job->uuid === self::UUID);
        $this->segment(1)->assertStatus(409);
        $this->assertTrue(AccessLogs::query()->where('user_id', $this->user->id)->where('action', 'session_transcription_consent_revoked')->exists());
    }

    public function testItDiscardsWhenPatientRevokesBeforeAnySegment(): void
    {
        $this->started();

        $this->consent('revoked')->assertOk()->assertJsonPath('status', 'discarded');
        Queue::assertNotPushed(ProcessSessionTranscriptionJob::class);
    }

    public function testItDiscardsTranscriptionWithoutSegments(): void
    {
        $this->started();

        $this->postAs('transcriptions/'.self::UUID.'/complete')->assertOk()->assertJsonPath('status', 'discarded');
        Queue::assertNotPushed(ProcessSessionTranscriptionJob::class);
    }

    public function testItCountsMinutesOnComplete(): void
    {
        $this->started();

        $this->transcription()->update(['started_at' => now()->subMinutes(50)]);
        $this->segment()->assertOk();
        $this->postAs('transcriptions/'.self::UUID.'/complete')->assertOk();

        $usage = UserFeatureUsages::query()->where('user_id', $this->user->id)->where('feature', 'transcription_minutes')->value('usage_count');

        $this->assertGreaterThanOrEqual(50, (int) $usage);
    }

    public function testItBlocksStartWhenMinutesQuotaIsExhausted(): void
    {
        config(['plans.plans.professional.total_transcription_minutes' => 0]);

        $this->postAs('transcriptions', ['uuid' => self::UUID])->assertForbidden()->assertJsonPath('code', 'quota');
    }

    public function testItOffersFreePlanMinutes(): void
    {
        $this->user->update(['current_plan' => 'free']);

        $this->withHeader('Authorization', 'Bearer '.$this->jwt())
            ->getJson('/api/video-room/transcriptions/context')
            ->assertJsonPath('can_start', true)
            ->assertJsonPath('minutes_left', config('plans.plans.free.total_transcription_minutes'));
    }

    public function testItProcessesIntoEncryptedDraftAndRemovesTemporary(): void
    {
        $this->notes->shouldReceive('draft')->once()->withArgs(function (string $transcript) {
            // Lacuna entre o fim do trecho 0 (2 min) e o início do 1 (3 min).
            return str_contains($transcript, '[00:01] Psicóloga: Como foi?')
                && str_contains($transcript, '[00:03] Paciente: Dormi melhor')
                && str_contains($transcript, '[02:00] (trecho não gravado: 1 min)');
        })->andReturn(self::DRAFT);
        $this->aws->shouldReceive('removeSession')->once()->with(self::UUID, 0);

        $this->pendingReview();

        $transcription = $this->transcription();
        $raw = Storage::disk('s3')->get(SessionTranscriptionService::notePath(self::UUID));
        $note = $this->openNote();

        $this->assertStringNotContainsString('Dormi', $raw);
        $this->assertSame(self::DRAFT, $note['draft']);
        $this->assertSame(self::UUID, $note['uuid']);
        $this->assertSame(['channel' => 1, 'startMs' => 183000, 'endMs' => 183900, 'text' => 'Dormi melhor'], $note['transcript'][3]);
        $this->assertSame([['startMs' => 120000, 'endMs' => 180000]], $note['gaps']);
        $this->assertSame(0, $transcription->parts_count);
        $this->assertNotNull($transcription->review_deadline);
        $this->assertSame(0, MedicalRecords::query()->where('patient_id', $this->patient->id)->count());
    }

    public function testItWaitsWhileTranscribeIsRunning(): void
    {
        $this->aws->shouldReceive('jobStatus')->andReturn('IN_PROGRESS');
        $this->notes->shouldNotReceive('draft');

        $this->processing();

        $this->assertFalse($this->process());
        $this->assertSame(SessionTranscriptionStatusEnum::PROCESSING, $this->transcription()->status);
    }

    public function testItKeepsTranscriptWithEmptyDraftWhenModelRefuses(): void
    {
        $this->notes->shouldReceive('draft')->andReturn(null);

        $this->pendingReview();

        $note = $this->openNote();

        $this->assertSame(['evolucao' => '', 'procedimentos' => '', 'observacoes' => ''], $note['draft']);
        $this->assertNotEmpty($note['transcript']);
    }

    public function testItDiscardsWhenNothingWasTranscribed(): void
    {
        $this->aws->shouldReceive('readOutput')->andReturn(['results' => ['channel_labels' => ['channels' => []]]]);
        $this->notes->shouldNotReceive('draft');

        $this->processing();

        $this->assertTrue($this->process());
        $this->assertSame(SessionTranscriptionStatusEnum::DISCARDED, $this->transcription()->status);
        Storage::disk('s3')->assertMissing(SessionTranscriptionService::notePath(self::UUID));
    }

    public function testItTurnsFailedSegmentIntoGap(): void
    {
        $this->aws->shouldReceive('jobStatus')->with(self::UUID, 0)->andReturn('FAILED');
        $this->aws->shouldReceive('jobStatus')->with(self::UUID, 1)->andReturn('COMPLETED');

        $this->pendingReview();

        $this->assertSame([['startMs' => 0, 'endMs' => 180000]], $this->openNote()['gaps']);
    }

    public function testItFailsAndDeletesEverythingWhenJobGivesUp(): void
    {
        $this->aws->shouldReceive('removeSession')->once();

        $this->processing();

        (new ProcessSessionTranscriptionJob(self::UUID))->failed(new \RuntimeException('Bedrock fora'));

        $this->assertSame(SessionTranscriptionStatusEnum::FAILED, $this->transcription()->status);
        $this->assertSame(0, $this->transcription()->parts_count);
    }

    public function testItCreatesRecordAndRemovesDraftOnAccept(): void
    {
        $this->pendingReview();

        $this->actingAs($this->user)
            ->postJson('/session-transcriptions/'.self::UUID.'/accept', ['notes_path' => 'medical-records/novo.bin'])
            ->assertOk();

        $transcription = $this->transcription();
        $record = MedicalRecords::query()->findOrFail($transcription->medical_record_id);

        $this->assertSame(SessionTranscriptionStatusEnum::ACCEPTED, $transcription->status);
        $this->assertSame('Registro da sessão', $record->title);
        $this->assertSame('medical-records/novo.bin', $record->notes_path);
        $this->assertNull($record->agenda_id);
        Storage::disk('s3')->assertMissing(SessionTranscriptionService::notePath(self::UUID));
    }

    public function testItDeletesFilesImmediatelyOnRejectAndKeepsConsent(): void
    {
        $this->pendingReview();

        $this->actingAs($this->user)->deleteJson('/session-transcriptions/'.self::UUID)->assertOk();

        $transcription = $this->transcription();

        $this->assertSame(SessionTranscriptionStatusEnum::REJECTED, $transcription->status);
        $this->assertNull($transcription->medical_record_id);
        $this->assertCount(1, $transcription->consents);
        Storage::disk('s3')->assertMissing(SessionTranscriptionService::notePath(self::UUID));
    }

    public function testItDeniesReviewOfAnotherPsychologist(): void
    {
        $this->pendingReview();
        $other = User::factory()->create();

        $this->actingAs($other)->get('/session-transcriptions/'.self::UUID)->assertNotFound();
        $this->assertGreaterThanOrEqual(400, $this->actingAs($other)->deleteJson('/session-transcriptions/'.self::UUID)->status());
        $this->assertSame(SessionTranscriptionStatusEnum::PENDING_REVIEW, $this->transcription()->status);
        Storage::disk('s3')->assertExists(SessionTranscriptionService::notePath(self::UUID));
    }

    public function testItLogsViewOnlyWhenNoteIsRequestedAndListsDrafts(): void
    {
        $this->pendingReview();
        $viewed = fn () => AccessLogs::query()->where('user_id', $this->user->id)->where('action', 'session_transcription_viewed')->count();

        $this->actingAs($this->user)->get('/session-transcriptions/'.self::UUID)
            ->assertOk()
            ->assertSee('Rascunho gerado por IA', false)
            ->assertSee('Aprovar e salvar no prontuário', false);
        $this->assertSame(0, $viewed());

        $this->actingAs($this->user)->getJson('/session-transcriptions/'.self::UUID.'/note')->assertOk()->assertJsonStructure(['link']);
        $this->assertSame(1, $viewed());

        $this->actingAs($this->user)->getJson('/session-transcriptions/paginate')
            ->assertOk()
            ->assertSee('Abrir rascunho', false)
            ->assertSee('js-reject-session-transcription', false);
    }

    public function testItDeniesNoteOfAnotherPsychologistAndAfterDecision(): void
    {
        $this->pendingReview();

        $this->actingAs(User::factory()->create())->getJson('/session-transcriptions/'.self::UUID.'/note')->assertStatus(400);
        $this->assertFalse(AccessLogs::query()->where('action', 'session_transcription_viewed')->exists());

        $this->actingAs($this->user)->deleteJson('/session-transcriptions/'.self::UUID)->assertOk();
        $this->actingAs($this->user)->getJson('/session-transcriptions/'.self::UUID.'/note')->assertStatus(400);
    }

    public function testItWarnsAboutRecordLimitBeforeOpeningDraftAndStillAllowsRejecting(): void
    {
        $this->pendingReview();
        config(['plans.plans.professional.total_medical_records' => 0]);

        $this->actingAs($this->user)->get('/session-transcriptions/'.self::UUID)
            ->assertOk()
            ->assertSee('Seu plano chegou ao limite', false)
            ->assertDontSee('initSessionTranscriptionReview', false);

        $this->actingAs($this->user)->deleteJson('/session-transcriptions/'.self::UUID)->assertOk();
        Storage::disk('s3')->assertMissing(SessionTranscriptionService::notePath(self::UUID));
    }

    public function testItDoesNotCreateSecondRecordWhenAcceptIsRepeated(): void
    {
        $this->pendingReview();
        $accept = fn () => $this->actingAs($this->user)->postJson('/session-transcriptions/'.self::UUID.'/accept', ['notes_path' => 'medical-records/novo.bin']);

        $accept()->assertOk();
        $accept()->assertStatus(400)->assertJsonPath('message', __('session-transcriptions.draft_unavailable'));

        $this->assertSame(1, MedicalRecords::query()->where('patient_id', $this->patient->id)->count());
    }

    public function testItKeepsDraftAndAlertsWhenRemovalFails(): void
    {
        $this->pendingReview();
        Bus::fake([RegisterLog::class]);
        Storage::shouldReceive('deleteDirectory')->once()->andThrow(new \RuntimeException('s3 fora'));

        $this->app->make(SessionTranscriptionService::class)->removeDraft(self::UUID);

        Bus::assertDispatchedSync(RegisterLog::class);
        $this->assertSame(SessionTranscriptionStatusEnum::PENDING_REVIEW, $this->transcription()->status);
    }

    public function testItKeepsTemporaryMarkedWhenAwsRemovalFails(): void
    {
        $this->aws->shouldReceive('removeSession')->andThrow(new \RuntimeException('aws fora'));

        $this->pendingReview();

        $this->assertSame(1, $this->transcription()->parts_count);
    }

    public function testItExpiresPendingAfterDeadlineAndDeletesFiles(): void
    {
        $this->pendingReview();
        $this->transcription()->update(['review_deadline' => now()->subMinute()]);

        Artisan::call('app:expire-session-transcriptions-command');

        $this->assertSame(SessionTranscriptionStatusEnum::EXPIRED, $this->transcription()->status);
        Storage::disk('s3')->assertMissing(SessionTranscriptionService::notePath(self::UUID));
    }

    public function testItClosesAbandonedRecordingAfterGracePeriod(): void
    {
        $this->started();
        $this->segment()->assertOk();
        $this->videoRoom->update(['ends_at' => now()->subHours(7)]);

        Artisan::call('app:expire-session-transcriptions-command');

        $this->assertSame(SessionTranscriptionStatusEnum::PROCESSING, $this->transcription()->status);
        Queue::assertPushed(ProcessSessionTranscriptionJob::class);
    }

    public function testItFailsProcessingStuckPastDeadline(): void
    {
        $this->processing();
        $this->transcription()->update(['ended_at' => now()->subHours(22)]);

        Artisan::call('app:expire-session-transcriptions-command');

        $this->assertSame(SessionTranscriptionStatusEnum::FAILED, $this->transcription()->status);
    }

    public function testItRemindsWithoutClinicalData(): void
    {
        $this->pendingReview();
        $this->transcription()->update(['review_deadline' => now()->addDays(7)->subMinutes(10)]);

        Artisan::call('app:expire-session-transcriptions-command');

        Mail::assertSent(SessionTranscriptionReminderMail::class, function (SessionTranscriptionReminderMail $mail) {
            return $mail->days === 7 && ! str_contains($mail->render(), 'Paciente Teste');
        });
    }

    public function testItDeletesDraftAndTemporaryWhenPatientIsPurged(): void
    {
        $this->started();
        $this->segment()->assertOk();
        $this->aws->shouldReceive('removeSession')->once()->with(self::UUID, 0);
        $this->patient->delete();

        Artisan::call('app:delete-patients-command');

        $this->assertDatabaseMissing('session_transcriptions', ['uuid' => self::UUID]);
    }

    public function testItNeverLogsSignedUrl(): void
    {
        $this->started();
        $this->segment()->assertOk();

        $logs = AccessLogs::query()->where('user_id', $this->user->id)->get()->toJson();

        $this->assertStringNotContainsString('bucket.teste', $logs);
    }

    public function testItAllowsCorsOnlyFromJitsiOrigin(): void
    {
        config(['cors.allowed_origins' => ['https://meet.teste']]);

        $this->withHeaders(['Origin' => 'https://meet.teste', 'Access-Control-Request-Method' => 'POST'])
            ->options('/api/video-room/transcriptions')
            ->assertHeader('Access-Control-Allow-Origin', 'https://meet.teste');

        $response = $this->withHeaders(['Origin' => 'https://outro.teste', 'Access-Control-Request-Method' => 'POST'])
            ->options('/api/video-room/transcriptions');

        $this->assertNotSame('https://outro.teste', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testItKeepsConsentProofWithTextAfterContentIsDeleted(): void
    {
        $this->pendingReview();
        $this->actingAs($this->user)->deleteJson('/session-transcriptions/'.self::UUID)->assertOk();

        $this->actingAs($this->user)
            ->get('/session-transcriptions/'.self::UUID.'/consent')
            ->assertOk()
            ->assertSee(__('session-transcriptions.decision.accepted'))
            ->assertSee(config('session_transcription.consent_text'))
            ->assertSee(hash('sha256', config('session_transcription.consent_text')));

        $this->actingAs($this->user)->getJson('/session-transcriptions/history/paginate')->assertOk();
        $this->actingAs($this->user)->get('/session-transcriptions/history')->assertOk();
    }

    public function testItDeniesConsentProofOfAnotherPsychologist(): void
    {
        $this->pendingReview();

        $this->actingAs(User::factory()->create())->get('/session-transcriptions/'.self::UUID.'/consent')->assertNotFound();
    }

    public function testItHidesHistoryWhileDisabled(): void
    {
        config(['session_transcription.enabled' => false]);

        $this->actingAs($this->user)->get('/session-transcriptions/history')->assertNotFound();
    }

    public function testItReportsMinutesLeftInContext(): void
    {
        config(['plans.plans.professional.total_transcription_minutes' => 100]);

        $this->withHeader('Authorization', 'Bearer '.$this->jwt())
            ->getJson('/api/video-room/transcriptions/context')
            ->assertJsonPath('minutes_left', 100);
    }

    public function testItShowsMinutesUsageOnPendingPageAndPlans(): void
    {
        $page = $this->actingAs($this->user)->get('/session-transcriptions')->assertOk();
        $page->assertSee(__('session-transcriptions.usage', ['used' => 0, 'limit' => 600]));

        $this->actingAs($this->user)->get('/plans')->assertOk()->assertSee('transcription-usage');
    }

    public function testItWarnsOnPatientPage(): void
    {
        $this->pendingReview();

        $this->actingAs($this->user)
            ->get(route('patients.show', ['id' => encrypt($this->patient->id)]))
            ->assertOk()
            ->assertSee(trans_choice('session-transcriptions.patient_banner', 1, ['count' => 1]));
    }
}
