<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\VideoRoomStatusEnum;
use App\Jobs\RegisterLog;
use App\Models\VideoRoom;
use App\Services\JitsiTokenIssuer;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class VideoRoomsTest extends TestCase
{
    use DatabaseTransactions;

    private const string TOKEN = 'token-de-servico-teste';

    private const string SECRET = 'segredo-do-prosody-para-testes-com-32-bytes';

    private const string MODERATOR_CPF = '529.982.247-25';

    private const string GUEST_CPF = '11144477735';

    private const string OTHER_CPF = '39053344705';

    private const string GUEST_PHONE = '11988887777';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'psi_video.jitsi_base_url' => 'https://meet.teste',
            'psi_video.jitsi.app_id' => 'psi-teste',
            'psi_video.jitsi.app_secret' => self::SECRET,
            'psi_video.jitsi.sub' => 'meet.teste',
            'psi_video.jitsi.aud' => 'jitsi',
            'psi_video.api_token' => self::TOKEN,
            'psi_video.cpf_pepper' => 'pepper-de-cpf-com-32-bytes-aleatorios',
            'psi_video.phone_pepper' => 'pepper-de-teste-com-32-bytes-aleatorios',
            'psi_video.access_opens_before_minutes' => 15,
            'psi_video.access_closes_after_minutes' => 30,
            'cors.allowed_origins' => ['https://meet.teste'],
        ]);

        // Horário da sessão: 20/10/2026 14:00 em São Paulo = 17:00 UTC
        Carbon::setTestNow(Carbon::parse('2026-10-20T17:00:00Z'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'session_id' => 'agenda-123',
            'starts_at' => '2026-10-20T14:00:00-03:00',
            'ends_at' => '2026-10-20T14:50:00-03:00',
            'participants' => [
                ['cpf' => self::MODERATOR_CPF, 'name' => 'Dra. Ana Souza', 'role' => 'moderator'],
                ['cpf' => self::GUEST_CPF, 'name' => 'João', 'role' => 'guest'],
            ],
        ], $overrides);
    }

    private function createRoom(array $overrides = []): string
    {
        return $this->withToken(self::TOKEN)
            ->postJson('/api/video-rooms', $this->payload($overrides))
            ->assertCreated()
            ->json('room');
    }

    private function access(string $room, string $cpf): TestResponse
    {
        return $this->postJson('/api/video-room/access', ['room' => $room, 'cpf' => $cpf]);
    }

    private function accessByPhone(string $room, string $phone): TestResponse
    {
        return $this->postJson('/api/video-room/access', ['room' => $room, 'phone' => $phone]);
    }

    private function createRoomWithPhoneGuest(): string
    {
        return $this->createRoom(['participants' => [
            ['cpf' => self::MODERATOR_CPF, 'phone' => '11999990000', 'name' => 'Dra. Ana Souza', 'role' => 'moderator'],
            ['phone' => self::GUEST_PHONE, 'name' => 'Pierre', 'role' => 'guest'],
        ]]);
    }

    private function decode(string $jwt): array
    {
        // Valida nbf/exp no horário simulado do teste
        JWT::$timestamp = Carbon::now()->getTimestamp();

        return json_decode((string) json_encode(JWT::decode($jwt, new Key(self::SECRET, 'HS256'))), true);
    }

    public function testItStoreRoomWithParticipants(): void
    {
        $response = $this->withToken(self::TOKEN)->postJson('/api/video-rooms', $this->payload());

        $room = $response->json('room');

        $response->assertCreated()->assertExactJson([
            'room' => $room,
            'url' => 'https://meet.teste/'.$room,
            'starts_at' => '2026-10-20T17:00:00Z',
            'ends_at' => '2026-10-20T17:50:00Z',
            'status' => 'active',
        ]);

        $this->assertMatchesRegularExpression('/^[a-z0-9]{12}$/', $room);

        $videoRoom = VideoRoom::query()->where('room', $room)->firstOrFail();
        $this->assertSame('agenda-123', $videoRoom->session_id);
        $this->assertCount(2, $videoRoom->participants);
    }

    public function testItStoreCpfHmacInsteadOfCpf(): void
    {
        $room = $this->createRoom();

        $participants = VideoRoom::query()->where('room', $room)->firstOrFail()->participants;

        $this->assertContains(hash_hmac('sha256', '52998224725', 'pepper-de-cpf-com-32-bytes-aleatorios'), $participants->pluck('cpf_hmac')->all());
        $this->assertContains(hash_hmac('sha256', '11144477735', 'pepper-de-cpf-com-32-bytes-aleatorios'), $participants->pluck('cpf_hmac')->all());
        $this->assertSame([null, null], $participants->pluck('phone_hmac')->all());

        $this->assertDatabaseMissing('video_room_participants', ['cpf_hmac' => '52998224725']);
    }

    public function testItStoreOnlyPhoneHmacForGuestWithoutCpf(): void
    {
        $room = $this->createRoomWithPhoneGuest();

        $participants = VideoRoom::query()->where('room', $room)->firstOrFail()->participants;

        // A psicóloga enviou telefone também, mas ele não fica guardado
        $this->assertNull($participants->firstWhere('name', 'Dra. Ana Souza')->phone_hmac);
        $this->assertNull($participants->firstWhere('name', 'Pierre')->cpf_hmac);
        $this->assertSame(hash_hmac('sha256', '+5511988887777', 'pepper-de-teste-com-32-bytes-aleatorios'), $participants->firstWhere('name', 'Pierre')->phone_hmac);
    }

    public function testItRejectStoreWithoutModerator(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson('/api/video-rooms', $this->payload([
                'participants' => [['cpf' => self::GUEST_CPF, 'name' => 'João', 'role' => 'guest']],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('participants');
    }

    public function testItRejectStoreWithDuplicatedCpfInDifferentFormats(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson('/api/video-rooms', $this->payload([
                'participants' => [
                    ['cpf' => '529.982.247-25', 'name' => 'Dra. Ana Souza', 'role' => 'moderator'],
                    ['cpf' => '52998224725', 'name' => 'Outra pessoa', 'role' => 'guest'],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('participants.1.cpf');
    }

    public function testItRejectStoreWithDuplicatedPhoneInDifferentFormats(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson('/api/video-rooms', $this->payload([
                'participants' => [
                    ['cpf' => self::MODERATOR_CPF, 'name' => 'Dra. Ana Souza', 'role' => 'moderator'],
                    ['phone' => '(11) 98888-7777', 'name' => 'João', 'role' => 'guest'],
                    ['phone' => '+55 11 98888-7777', 'name' => 'Outra pessoa', 'role' => 'guest'],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('participants.2.phone');
    }

    public function testItRejectStoreWithInvalidCpfOrPhone(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson('/api/video-rooms', $this->payload([
                'participants' => [
                    ['cpf' => '111.111.111-11', 'name' => 'Dra. Ana Souza', 'role' => 'moderator'],
                    ['phone' => '123', 'name' => 'João', 'role' => 'guest'],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['participants.0.cpf', 'participants.1.phone']);
    }

    public function testItRejectStoreWithModeratorWithoutCpf(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson('/api/video-rooms', $this->payload([
                'participants' => [['phone' => '11999990000', 'name' => 'Dra. Ana Souza', 'role' => 'moderator']],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('participants.0.cpf');
    }

    public function testItRejectStoreWithGuestWithoutCpfAndPhone(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson('/api/video-rooms', $this->payload([
                'participants' => [
                    ['cpf' => self::MODERATOR_CPF, 'name' => 'Dra. Ana Souza', 'role' => 'moderator'],
                    ['name' => 'João', 'role' => 'guest'],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('participants.1.cpf');
    }

    public function testItRejectStoreWhenEndsAtIsNotAfterStartsAt(): void
    {
        $this->withToken(self::TOKEN)
            ->postJson('/api/video-rooms', $this->payload(['ends_at' => '2026-10-20T14:00:00-03:00']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('ends_at');
    }

    public function testItRejectStoreWithoutAuthentication(): void
    {
        $this->postJson('/api/video-rooms', $this->payload())->assertUnauthorized();
        $this->withToken('token-errado')->postJson('/api/video-rooms', $this->payload())->assertUnauthorized();
    }

    public function testItShowRoomWithoutCredentials(): void
    {
        $room = $this->createRoom();

        $response = $this->withToken(self::TOKEN)->getJson('/api/video-rooms/'.$room)->assertOk();

        $response->assertJsonPath('url', 'https://meet.teste/'.$room)
            ->assertJsonPath('participants', [
                ['name' => 'Dra. Ana Souza', 'role' => 'moderator'],
                ['name' => 'João', 'role' => 'guest'],
            ]);

        $this->withToken(self::TOKEN)->getJson('/api/video-rooms/naoexiste123')->assertNotFound();
    }

    public function testItAccessAsModeratorWithAnyCpfFormat(): void
    {
        $room = $this->createRoom();

        foreach (['529.982.247-25', '52998224725', ' 529 982 247 25 '] as $cpf) {
            $response = $this->access($room, $cpf)->assertOk();

            $response->assertJsonPath('name', 'Dra. Ana Souza')
                ->assertJsonPath('role', 'moderator')
                ->assertJsonPath('expires_at', '2026-10-20T18:20:00Z');

            $claims = $this->decode($response->json('jwt'));

            $this->assertSame('psi-teste', $claims['iss']);
            $this->assertSame('jitsi', $claims['aud']);
            $this->assertSame('meet.teste', $claims['sub']);
            $this->assertSame($room, $claims['room']);
            $this->assertSame(Carbon::parse('2026-10-20T18:20:00Z')->getTimestamp(), $claims['exp']);
            $this->assertTrue($claims['context']['user']['moderator']);
            $this->assertSame('owner', $claims['context']['user']['affiliation']);
            $this->assertSame('Dra. Ana Souza', $claims['context']['user']['name']);
            $this->assertSame(16, strlen($claims['context']['user']['id']));
        }
    }

    public function testItAccessAsGuest(): void
    {
        $room = $this->createRoom();

        $response = $this->access($room, self::GUEST_CPF)->assertOk()->assertJsonPath('role', 'guest');

        $claims = $this->decode($response->json('jwt'));

        $this->assertFalse($claims['context']['user']['moderator']);
        $this->assertSame('member', $claims['context']['user']['affiliation']);
        $this->assertSame('João', $claims['context']['user']['name']);
    }

    public function testItAccessAsGuestWithPhoneWhenGuestHasNoCpf(): void
    {
        $room = $this->createRoomWithPhoneGuest();

        $response = $this->accessByPhone($room, '(11) 98888-7777')
            ->assertOk()
            ->assertJsonPath('name', 'Pierre')
            ->assertJsonPath('role', 'guest');

        $claims = $this->decode($response->json('jwt'));

        $this->assertFalse($claims['context']['user']['moderator']);
        $this->assertSame(16, strlen($claims['context']['user']['id']));
    }

    public function testItDenyPhoneOfModeratorEvenWhenStoredByMistake(): void
    {
        // Sala com telefone gravado direto na moderadora: o telefone continua sem abrir o papel de moderador
        $room = $this->createRoom();
        $videoRoom = VideoRoom::query()->where('room', $room)->firstOrFail();
        $videoRoom->participants()->where('role', 'moderator')->update([
            'phone_hmac' => hash_hmac('sha256', '+5511999990000', 'pepper-de-teste-com-32-bytes-aleatorios'),
        ]);

        $this->accessByPhone($room, '11999990000')->assertForbidden();
    }

    public function testItDenyPhoneOfGuestWithCpf(): void
    {
        $room = $this->createRoom(['participants' => [
            ['cpf' => self::MODERATOR_CPF, 'name' => 'Dra. Ana Souza', 'role' => 'moderator'],
            ['cpf' => self::GUEST_CPF, 'phone' => self::GUEST_PHONE, 'name' => 'João', 'role' => 'guest'],
        ]]);

        $this->accessByPhone($room, self::GUEST_PHONE)->assertForbidden();
        $this->access($room, self::GUEST_CPF)->assertOk();
    }

    public function testItDenyAccessWithSameResponseForUnknownCredentialRoomAndCancelledRoom(): void
    {
        $room = $this->createRoom();
        $cancelled = $this->createRoom();

        $this->withToken(self::TOKEN)->deleteJson('/api/video-rooms/'.$cancelled)->assertNoContent();
        $this->assertSame(VideoRoomStatusEnum::CANCELLED, VideoRoom::query()->where('room', $cancelled)->firstOrFail()->status);

        $expected = ['message' => 'Não encontramos este CPF ou telefone nesta sessão.'];

        $this->access($room, self::OTHER_CPF)->assertForbidden()->assertExactJson($expected);
        $this->access('naoexiste123', self::MODERATOR_CPF)->assertForbidden()->assertExactJson($expected);
        // Limite por sala + IP: cada sala aceita 5 tentativas
        $this->access($cancelled, self::MODERATOR_CPF)->assertForbidden()->assertExactJson($expected);
        $this->access($room, '111.111.111-11')->assertForbidden()->assertExactJson($expected);
        $this->accessByPhone($room, 'abc')->assertForbidden()->assertExactJson($expected);
    }

    public function testItRequireRoomAndCredential(): void
    {
        $this->postJson('/api/video-room/access', ['room' => 'abc'])
            ->assertStatus(422)
            ->assertExactJson(['message' => 'Informe o CPF ou o telefone.']);
    }

    public function testItRespectAccessWindow(): void
    {
        $room = $this->createRoom();

        Carbon::setTestNow(Carbon::parse('2026-10-20T16:40:00Z'));
        $this->access($room, self::MODERATOR_CPF)
            ->assertStatus(410)
            ->assertExactJson(['message' => 'Esta sessão ainda não começou. O acesso é liberado 15 minutos antes do horário.']);

        Carbon::setTestNow(Carbon::parse('2026-10-20T16:50:00Z'));
        $this->access($room, self::MODERATOR_CPF)->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-10-20T18:21:00Z'));
        $this->access($room, self::MODERATOR_CPF)
            ->assertStatus(410)
            ->assertExactJson(['message' => 'Esta sessão já foi encerrada.']);
    }

    public function testItAllowAccessAnytimeWhenWindowIsNotConfigured(): void
    {
        config(['psi_video.access_opens_before_minutes' => null, 'psi_video.access_closes_after_minutes' => null]);
        $room = $this->createRoom();

        Carbon::setTestNow(Carbon::parse('2026-10-18T09:00:00Z'));
        $this->access($room, self::MODERATOR_CPF)
            ->assertOk()
            ->assertJsonPath('expires_at', '2026-10-20T18:50:00Z');

        Carbon::setTestNow(Carbon::parse('2026-10-25T09:00:00Z'));
        $response = $this->access($room, self::GUEST_CPF)
            ->assertOk()
            ->assertJsonPath('expires_at', '2026-10-25T10:00:00Z');

        $this->assertSame(Carbon::parse('2026-10-25T10:00:00Z')->getTimestamp(), $this->decode($response->json('jwt'))['exp']);
    }

    public function testItAllowAccessBeforeWhenOnlyOpensBeforeIsNotConfigured(): void
    {
        config(['psi_video.access_opens_before_minutes' => null]);
        $room = $this->createRoom();

        Carbon::setTestNow(Carbon::parse('2026-10-18T09:00:00Z'));
        $this->access($room, self::MODERATOR_CPF)->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-10-20T18:21:00Z'));
        $this->access($room, self::MODERATOR_CPF)
            ->assertStatus(410)
            ->assertExactJson(['message' => 'Esta sessão já foi encerrada.']);
    }

    public function testItAllowAccessAfterWhenOnlyClosesAfterIsNotConfigured(): void
    {
        config(['psi_video.access_closes_after_minutes' => null]);
        $room = $this->createRoom();

        Carbon::setTestNow(Carbon::parse('2026-10-20T16:40:00Z'));
        $this->access($room, self::MODERATOR_CPF)->assertStatus(410);

        Carbon::setTestNow(Carbon::parse('2026-10-25T09:00:00Z'));
        $this->access($room, self::MODERATOR_CPF)->assertOk();
    }

    public function testItTreatZeroMinutesAsExactTime(): void
    {
        config(['psi_video.access_opens_before_minutes' => 0, 'psi_video.access_closes_after_minutes' => 0]);
        $room = $this->createRoom();

        Carbon::setTestNow(Carbon::parse('2026-10-20T16:59:00Z'));
        $this->access($room, self::MODERATOR_CPF)->assertStatus(410);

        Carbon::setTestNow(Carbon::parse('2026-10-20T17:00:00Z'));
        $this->access($room, self::MODERATOR_CPF)->assertOk()->assertJsonPath('expires_at', '2026-10-20T17:50:00Z');

        Carbon::setTestNow(Carbon::parse('2026-10-20T17:51:00Z'));
        $this->access($room, self::MODERATOR_CPF)->assertStatus(410);
    }

    public function testItThrottleSixthAttemptInFifteenMinutes(): void
    {
        $room = $this->createRoom();

        for ($i = 0; $i < 5; $i++) {
            $this->access($room, self::OTHER_CPF)->assertForbidden();
        }

        $this->access($room, self::MODERATOR_CPF)->assertStatus(429);
    }

    public function testItThrottleByRoomAcrossDifferentIps(): void
    {
        $room = $this->createRoom();

        for ($i = 0; $i < 30; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.($i + 1)])
                ->access($room, self::OTHER_CPF)
                ->assertForbidden();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])->access($room, self::MODERATOR_CPF)->assertStatus(429);
    }

    public function testItDoesNotLogCpfOnException(): void
    {
        $room = $this->createRoom();

        Bus::fake();
        $this->mock(JitsiTokenIssuer::class)->shouldReceive('issue')->andThrow(new \RuntimeException('falha'));

        // document e payer_document: mesma lista vale para o agendamento público e o cadastro de paciente
        $this->postJson('/api/video-room/access', [
            'room' => $room,
            'cpf' => self::MODERATOR_CPF,
            'document' => '11144477735',
            'payer_document' => '39053344705',
        ])->assertServerError();

        Bus::assertDispatchedSync(RegisterLog::class, function (RegisterLog $job) use ($room) {
            $logged = (string) json_encode($job->exception);

            return str_contains($logged, $room)
                && ! str_contains($logged, self::MODERATOR_CPF)
                && ! str_contains($logged, '11144477735')
                && ! str_contains($logged, '39053344705');
        });
    }

    public function testItRescheduleKeepingRoomAndUrl(): void
    {
        $room = $this->createRoom();

        $this->withToken(self::TOKEN)
            ->patchJson('/api/video-rooms/'.$room, [
                'starts_at' => '2026-10-21T14:00:00-03:00',
                'ends_at' => '2026-10-21T14:50:00-03:00',
            ])
            ->assertOk()
            ->assertJsonPath('room', $room)
            ->assertJsonPath('url', 'https://meet.teste/'.$room)
            ->assertJsonPath('starts_at', '2026-10-21T17:00:00Z')
            ->assertJsonPath('participants.0.name', 'Dra. Ana Souza');

        Carbon::setTestNow(Carbon::parse('2026-10-20T17:00:00Z'));
        $this->access($room, self::MODERATOR_CPF)->assertStatus(410);

        Carbon::setTestNow(Carbon::parse('2026-10-21T17:00:00Z'));
        $this->access($room, self::MODERATOR_CPF)->assertOk()->assertJsonPath('expires_at', '2026-10-21T18:20:00Z');
    }

    public function testItReplaceParticipantsOnUpdate(): void
    {
        $room = $this->createRoom();

        $this->withToken(self::TOKEN)
            ->patchJson('/api/video-rooms/'.$room, [
                'participants' => [['cpf' => self::OTHER_CPF, 'name' => 'Dr. Carlos', 'role' => 'moderator']],
            ])
            ->assertOk()
            ->assertJsonPath('participants', [['name' => 'Dr. Carlos', 'role' => 'moderator']]);

        Carbon::setTestNow(Carbon::parse('2026-10-20T17:00:00Z'));
        $this->access($room, self::MODERATOR_CPF)->assertForbidden();
        $this->access($room, self::OTHER_CPF)->assertOk()->assertJsonPath('name', 'Dr. Carlos');
    }

    public function testItRejectUpdateWhenEndsAtBeforeCurrentStartsAt(): void
    {
        $room = $this->createRoom();

        $this->withToken(self::TOKEN)
            ->patchJson('/api/video-rooms/'.$room, ['ends_at' => '2026-10-20T13:00:00-03:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ends_at');
    }

    public function testItAnswerCorsPreflightFromJitsiOrigin(): void
    {
        $this->call('OPTIONS', '/api/video-room/access', [], [], [], [
            'HTTP_ORIGIN' => 'https://meet.teste',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://meet.teste')
            ->assertHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    }
}
