<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AgendaStatusEnum;
use App\Enums\GoogleCalendarStatusEnum;
use App\Jobs\SyncAgendaToGoogleJob;
use App\Models\Agenda;
use App\Models\GoogleCalendarAccount;
use App\Models\Patients;
use App\Models\PsychologistSettings;
use App\Models\User;
use App\Services\AgendaVideoRoomService;
use App\Services\GoogleCalendarService;
use App\Services\GoogleCalendarSyncService;
use App\Services\PsychologistSettingService;
use App\Wrappers\GoogleCalendarWrapper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class GoogleCalendarSyncServiceTest extends TestCase
{
    use DatabaseTransactions;

    private $wrapper;

    private $googleCalendarService;

    private GoogleCalendarSyncService $service;

    private User $user;

    private Patients $patient;

    protected function setUp(): void
    {
        parent::setUp();

        // Observers disparam jobs reais de sincronização/notificação
        Bus::fake();

        $this->wrapper = Mockery::mock(GoogleCalendarWrapper::class);
        $this->googleCalendarService = Mockery::mock(GoogleCalendarService::class);
        $this->googleCalendarService->shouldReceive('getValidAccessToken')->andReturn('token')->byDefault();

        $this->service = new GoogleCalendarSyncService(
            new Agenda,
            new GoogleCalendarAccount,
            $this->googleCalendarService,
            $this->wrapper,
            new AgendaVideoRoomService,
            app(PsychologistSettingService::class),
        );

        $this->user = User::factory()->create();
        $this->patient = Patients::query()->create([
            'user_id' => $this->user->id,
            'name' => 'Ana Souza',
            'phone' => '11999990000',
            'email' => 'ana@example.com',
        ]);
    }

    private function connect(array $override = []): GoogleCalendarAccount
    {
        return GoogleCalendarAccount::query()->create(array_merge([
            'user_id' => $this->user->id,
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
            'status' => GoogleCalendarStatusEnum::INTEGRATED->value,
        ], $override));
    }

    private function agenda(array $override = []): Agenda
    {
        return Agenda::query()->create(array_merge([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'type' => 'online',
            'scheduled_on' => '2030-10-07 12:00:00',
            'ends_at' => '2030-10-07 13:00:00',
            'status' => AgendaStatusEnum::SCHEDULED->value,
        ], $override));
    }

    public function testItSyncAgendaCreatesEventWithRoomLinkAsLocation(): void
    {
        $this->connect();
        $agenda = $this->agenda(['room_link' => 'https://meet.psi4.com.br/abc123def456']);

        $this->wrapper->shouldReceive('insertEvent')->once()->withArgs(function ($token, $calendar, $event, $notify) {
            return $calendar === 'primary'
                && $notify === false
                && $event['summary'] === 'Consulta – Ana'
                && $event['location'] === 'https://meet.psi4.com.br/abc123def456'
                && ! isset($event['attendees'])
                && ! isset($event['conferenceData'])
                && str_starts_with($event['id'], 'psi4');
        })->andReturnUsing(fn ($t, $c, $event) => ['id' => $event['id']]);

        $this->service->syncAgenda($agenda->id);

        $agenda->refresh();
        $this->assertSame('https://meet.psi4.com.br/abc123def456', $agenda->room_link);
        $this->assertNotNull($agenda->google_event_id);
        $this->assertNotNull($agenda->google_synced_at);
        // Já tinha link: o evento não precisa ser reenviado (só o despacho do observer na criação)
        Bus::assertDispatchedTimes(SyncAgendaToGoogleJob::class, 1);
    }

    public function testItSyncAgendaInvitesPatientWhenEnabled(): void
    {
        $this->connect(['invite_patient' => true, 'calendar_id' => 'consultas@group.calendar.google.com']);
        $agenda = $this->agenda(['room_link' => 'https://zoom.us/j/1']);

        $this->wrapper->shouldReceive('insertEvent')->once()->withArgs(function ($token, $calendar, $event, $notify) {
            return $calendar === 'consultas@group.calendar.google.com'
                && $notify === true
                && $event['attendees'] === [['email' => 'ana@example.com']];
        })->andReturnUsing(fn ($t, $c, $event) => ['id' => $event['id']]);

        $this->service->syncAgenda($agenda->id);

        $this->assertSame('https://zoom.us/j/1', $agenda->fresh()->room_link);
    }

    public function testItSyncAgendaAttachesPsi4RoomAndResyncsWhenAgendaHasNoLink(): void
    {
        $this->connect();
        $agenda = $this->agenda();

        $this->wrapper->shouldReceive('insertEvent')->once()
            ->andReturnUsing(fn ($t, $c, $event) => ['id' => $event['id']]);

        $this->service->syncAgenda($agenda->id);

        $this->assertJitsiLink($agenda->fresh()->room_link);
        // Além do despacho do observer na criação, o evento é reenviado para levar o link da sala como local
        Bus::assertDispatchedTimes(SyncAgendaToGoogleJob::class, 2);
    }

    public function testItSyncAgendaKeepsManualLinkAndSendsItAsLocation(): void
    {
        $this->connect();
        $agenda = $this->agenda(['room_link' => 'https://zoom.us/j/123']);

        $this->wrapper->shouldReceive('insertEvent')->once()->withArgs(function ($token, $calendar, $event) {
            return $event['location'] === 'https://zoom.us/j/123';
        })->andReturnUsing(fn ($t, $c, $event) => ['id' => $event['id']]);

        $this->service->syncAgenda($agenda->id);

        $this->assertSame('https://zoom.us/j/123', $agenda->fresh()->room_link);
    }

    public function testItSyncAgendaInPersonSendsAddressAsLocationWithoutRoom(): void
    {
        $this->connect();
        PsychologistSettings::query()->create([
            'user_id' => $this->user->id,
            'rua' => 'Rua das Flores',
            'numero' => '100',
            'cidade' => 'Campinas',
        ]);
        $agenda = $this->agenda(['type' => 'in_person']);

        $this->wrapper->shouldReceive('insertEvent')->once()->withArgs(function ($token, $calendar, $event) {
            return $event['location'] === 'Rua das Flores, 100 · Campinas';
        })->andReturnUsing(fn ($t, $c, $event) => ['id' => $event['id']]);

        $this->service->syncAgenda($agenda->id);

        $agenda->refresh();
        $this->assertNull($agenda->room_link);
        $this->assertNotNull($agenda->google_synced_at);
    }

    public function testItFallbackToVideoRoomIgnoresInPersonAgenda(): void
    {
        $agenda = $this->agenda(['type' => 'in_person']);

        $this->service->fallbackToVideoRoom($agenda->id);

        $this->assertNull($agenda->fresh()->room_link);
    }

    public function testItSyncAgendaPatchesExistingEvent(): void
    {
        $this->connect();
        $agenda = $this->agenda([
            'room_link' => 'https://meet.psi4.com.br/abc123def456',
            'google_event_id' => 'psi4abc',
            'google_synced_at' => now(),
        ]);

        $this->wrapper->shouldReceive('insertEvent')->never();
        $this->wrapper->shouldReceive('patchEvent')->once()->with('token', 'primary', 'psi4abc', Mockery::type('array'), false)
            ->andReturn(['id' => 'psi4abc']);

        $this->service->syncAgenda($agenda->id);

        $this->assertSame('https://meet.psi4.com.br/abc123def456', $agenda->fresh()->room_link);
    }

    public function testItSyncAgendaPatchesWhenInsertReportsExistingEvent(): void
    {
        $this->connect();
        $agenda = $this->agenda(['room_link' => 'https://zoom.us/j/1']);

        $this->wrapper->shouldReceive('insertEvent')->once()->andReturnNull();
        $this->wrapper->shouldReceive('patchEvent')->once()->andReturn(['id' => 'psi4x']);

        $this->service->syncAgenda($agenda->id);

        $this->assertSame('psi4x', $agenda->fresh()->google_event_id);
    }

    public function testItSyncAgendaRecreatesEventDeletedInGoogleWithNewId(): void
    {
        $this->connect();
        $agenda = $this->agenda(['room_link' => 'https://zoom.us/j/1', 'google_event_id' => 'psi4old', 'google_synced_at' => now()]);

        $this->wrapper->shouldReceive('patchEvent')->once()->andReturnNull();
        $this->wrapper->shouldReceive('insertEvent')->once()->withArgs(fn ($t, $c, $event) => $event['id'] !== 'psi4old')
            ->andReturnUsing(fn ($t, $c, $event) => ['id' => $event['id']]);

        $this->service->syncAgenda($agenda->id);

        $agenda->refresh();
        $this->assertNotSame('psi4old', $agenda->google_event_id);
        $this->assertNotNull($agenda->google_synced_at);
    }

    public function testItSyncAgendaSkipsCanceledAgenda(): void
    {
        $this->connect();
        $agenda = $this->agenda(['status' => AgendaStatusEnum::CANCELED->value]);

        $this->wrapper->shouldReceive('insertEvent')->never();

        $this->service->syncAgenda($agenda->id);

        $this->assertNull($agenda->fresh()->google_event_id);
    }

    public function testItSyncAgendaFallsBackToPsi4RoomWhenAccountIsNotConnected(): void
    {
        $agenda = $this->agenda();

        $this->wrapper->shouldReceive('insertEvent')->never();

        $this->service->syncAgenda($agenda->id);

        $this->assertJitsiLink($agenda->fresh()->room_link);
        Bus::assertNotDispatched(SyncAgendaToGoogleJob::class);
    }

    public function testItSyncAgendaFallsBackToPsi4RoomWhenGoogleRefusesPermanently(): void
    {
        $this->connect();
        $agenda = $this->agenda();

        $this->wrapper->shouldReceive('insertEvent')->once()->andThrow(new \DomainException('recusado'));

        $this->service->syncAgenda($agenda->id);

        $this->assertJitsiLink($agenda->fresh()->room_link);
        // Só o despacho do observer na criação: o evento não existe no Google, não há o que reenviar
        Bus::assertDispatchedTimes(SyncAgendaToGoogleJob::class, 1);
    }

    public function testItSyncAgendaRethrowsTransientFailure(): void
    {
        $this->connect();
        $agenda = $this->agenda();

        $this->wrapper->shouldReceive('insertEvent')->once()->andThrow(new \RuntimeException('timeout'));

        $this->expectException(\RuntimeException::class);

        $this->service->syncAgenda($agenda->id);
    }

    public function testItFallbackToVideoRoomKeepsExistingLink(): void
    {
        $withLink = $this->agenda(['room_link' => 'https://zoom.us/j/1']);

        $this->service->fallbackToVideoRoom($withLink->id);

        $this->assertSame('https://zoom.us/j/1', $withLink->fresh()->room_link);
    }

    public function testItFallbackToVideoRoomIgnoresCanceledAgenda(): void
    {
        $agenda = $this->agenda(['status' => AgendaStatusEnum::CANCELED->value]);

        $this->service->fallbackToVideoRoom($agenda->id);

        $this->assertNull($agenda->fresh()->room_link);
    }

    public function testItFallbackToVideoRoomIgnoresMissingAgenda(): void
    {
        $this->service->fallbackToVideoRoom(0);

        Bus::assertNotDispatched(SyncAgendaToGoogleJob::class);
    }

    private function assertJitsiLink(?string $link): void
    {
        $this->assertMatchesRegularExpression('~^'.preg_quote(config('psi_video.jitsi_base_url'), '~').'/[a-z0-9]{12}$~', (string) $link);
    }

    public function testItRemoveEventDeletesInGoogle(): void
    {
        $this->connect(['invite_patient' => true]);

        $this->wrapper->shouldReceive('deleteEvent')->once()->with('token', 'primary', 'psi4abc', true);

        $this->service->removeEvent($this->user->id, 'psi4abc');

        $this->addToAssertionCount(1);
    }

    public function testItRemoveEventDoesNothingWithoutConnectedAccount(): void
    {
        $this->wrapper->shouldReceive('deleteEvent')->never();

        $this->service->removeEvent($this->user->id, 'psi4abc');

        $this->addToAssertionCount(1);
    }

    public function testItRemoveEventIgnoresPermanentRefusal(): void
    {
        $this->connect();

        $this->wrapper->shouldReceive('deleteEvent')->once()->andThrow(new \DomainException('recusado'));

        $this->service->removeEvent($this->user->id, 'psi4abc');

        $this->addToAssertionCount(1);
    }
}
