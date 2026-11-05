<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\GoogleCalendarStatusEnum;
use App\Enums\VideoRoomRoleEnum;
use App\Enums\VideoRoomStatusEnum;
use App\Jobs\DeleteGoogleEventJob;
use App\Jobs\SyncAgendaToGoogleJob;
use App\Models\Agenda;
use App\Models\AgendaSettings;
use App\Models\GoogleCalendarAccount;
use App\Models\Patients;
use App\Models\User;
use App\Models\VideoRoom;
use App\Services\AgendaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AgendaServiceStoreRoomTest extends TestCase
{
    use DatabaseTransactions;

    private AgendaService $service;

    private User $user;

    private Patients $patient;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();

        $this->service = app(AgendaService::class);
        $this->user = User::factory()->create();
        $this->patient = Patients::query()->create(['user_id' => $this->user->id, 'name' => 'Ana', 'phone' => '11999990000']);
    }

    private function store(array $override = []): void
    {
        $this->service->store(array_merge([
            'patient_id' => $this->patient->id,
            'type' => 'online',
            'date' => '2030-10-07',
            'timer' => ['09:00'],
        ], $override), $this->user->id, 'UTC');
    }

    private function roomLinks(): array
    {
        return Agenda::query()->where('user_id', $this->user->id)->orderBy('scheduled_on')->pluck('room_link')->all();
    }

    private function assertJitsiLink(?string $link): void
    {
        $this->assertMatchesRegularExpression('~^'.preg_quote(config('psi_video.jitsi_base_url'), '~').'/[a-z0-9]{12}$~', (string) $link);
    }

    public function testItStoreGeneratesJitsiLinkWithoutGoogleAndDoesNotSync(): void
    {
        $this->store();

        $links = $this->roomLinks();
        $this->assertCount(1, $links);
        $this->assertJitsiLink($links[0]);
        Bus::assertNotDispatched(SyncAgendaToGoogleJob::class);
    }

    public function testItStoreCreatesVideoRoomWithPsychologistAndPatient(): void
    {
        $this->store();

        $agenda = Agenda::query()->where('user_id', $this->user->id)->first();
        $videoRoom = VideoRoom::query()->where('session_id', (string) $agenda->id)->with('participants')->sole();

        $this->assertSame($agenda->room_link, config('psi_video.jitsi_base_url').'/'.$videoRoom->room);
        $this->assertSame(VideoRoomStatusEnum::ACTIVE, $videoRoom->status);
        $this->assertTrue($videoRoom->starts_at->equalTo($agenda->scheduled_on));
        $this->assertTrue($videoRoom->ends_at->equalTo($agenda->ends_at));
        $this->assertSame(
            [[$this->user->name, VideoRoomRoleEnum::MODERATOR], ['Ana', VideoRoomRoleEnum::GUEST]],
            $videoRoom->participants->map(fn ($p) => [$p->name, $p->role])->all(),
        );
    }

    public function testItStoreThrowExceptionWhenPsychologistHasNoDocument(): void
    {
        $this->user->forceFill(['document' => null])->save();

        try {
            $this->store();
            $this->fail('DomainException esperada');
        } catch (\DomainException $e) {
            $this->assertSame(__('video-room.psychologist_document_required'), $e->getMessage());
        }

        // A transação desfaz o agendamento: não fica consulta online sem sala
        $this->assertSame([], $this->roomLinks());
    }

    public function testItStoreGeneratesUniqueJitsiLinkPerTimer(): void
    {
        $this->store(['timer' => ['09:00', '10:00', '11:00']]);

        $links = $this->roomLinks();
        $this->assertCount(3, $links);
        array_map(fn ($link) => $this->assertJitsiLink($link), $links);
        $this->assertCount(3, array_unique($links));
    }

    public function testItStoreKeepsManualRoomLinkWithoutGoogle(): void
    {
        $this->store(['room_link' => 'https://zoom.us/j/123']);

        $this->assertSame(['https://zoom.us/j/123'], $this->roomLinks());
    }

    public function testItStoreGeneratesJitsiLinkWhenGoogleNeedsReauth(): void
    {
        $this->connectGoogle(GoogleCalendarStatusEnum::NEEDS_REAUTH);

        $this->store();

        $this->assertJitsiLink($this->roomLinks()[0]);
        Bus::assertNotDispatched(SyncAgendaToGoogleJob::class);
    }

    public function testItStoreGeneratesNewJitsiLinkWhenReusingCanceledSlot(): void
    {
        $this->store();
        $agenda = Agenda::query()->where('user_id', $this->user->id)->first();
        $oldLink = $agenda->room_link;
        $this->service->cancel($agenda->id, $this->user->id);

        $this->store();

        $newLink = $agenda->fresh()->room_link;
        $this->assertJitsiLink($newLink);
        $this->assertNotSame($oldLink, $newLink);
        $this->assertSame(
            [VideoRoomStatusEnum::CANCELLED, VideoRoomStatusEnum::ACTIVE],
            VideoRoom::query()->where('session_id', (string) $agenda->id)->orderBy('id')->pluck('status')->all(),
        );
    }

    public function testItCancelClosesVideoRoom(): void
    {
        $this->store();
        $agenda = Agenda::query()->where('user_id', $this->user->id)->first();

        $this->service->cancel($agenda->id, $this->user->id);

        $this->assertSame(VideoRoomStatusEnum::CANCELLED, VideoRoom::query()->where('session_id', (string) $agenda->id)->value('status'));
    }

    public function testItStoreInPersonDoesNotGenerateRoomLink(): void
    {
        $this->store(['type' => 'in_person']);

        $this->assertSame([null], $this->roomLinks());
        $this->assertSame(0, VideoRoom::query()->whereIn('session_id', Agenda::query()->where('user_id', $this->user->id)->pluck('id')->map('strval'))->count());
    }

    public function testItStoreInPersonIgnoresManualRoomLink(): void
    {
        $this->store(['type' => 'in_person', 'room_link' => 'https://zoom.us/j/123']);

        $this->assertSame([null], $this->roomLinks());
    }

    public function testItStoreInPersonWithGoogleConnectedStillSyncsEvent(): void
    {
        $this->connectGoogle();

        $this->store(['type' => 'in_person']);

        $this->assertSame([null], $this->roomLinks());
        Bus::assertDispatched(SyncAgendaToGoogleJob::class);
    }

    public function testItStoreByPsychologistThrowExceptionWhenTypeNotAllowedInPeriod(): void
    {
        AgendaSettings::query()->create([
            'user_id' => $this->user->id,
            'consultation_time' => '01:00:00',
            'available_time_minutes' => '00:00:00',
            'attendance_mode' => 'both',
            'agenda_days' => ['seg' => ['enabled' => '1', 'periods' => [
                ['start' => '08:00', 'end' => '12:00', 'mode' => 'online'],
                ['start' => '14:00', 'end' => '18:00', 'mode' => 'in_person'],
            ]]],
            'lunch_enabled' => false,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('agenda.mode_not_allowed'));

        // 2030-10-07 é segunda-feira
        $this->service->storeByPsychologist([
            'patient_id' => $this->patient->id,
            'type' => 'in_person',
            'date' => '2030-10-07',
            'timer' => ['09:00'],
        ], $this->user->id, 'UTC');
    }

    public function testItStoreByPsychologistAcceptsTypeAllowedInPeriod(): void
    {
        AgendaSettings::query()->create([
            'user_id' => $this->user->id,
            'consultation_time' => '01:00:00',
            'available_time_minutes' => '00:00:00',
            'attendance_mode' => 'both',
            'agenda_days' => ['seg' => ['enabled' => '1', 'periods' => [
                ['start' => '08:00', 'end' => '12:00', 'mode' => 'online'],
                ['start' => '14:00', 'end' => '18:00', 'mode' => 'in_person'],
            ]]],
            'lunch_enabled' => false,
        ]);

        $this->service->storeByPsychologist([
            'patient_id' => $this->patient->id,
            'type' => 'in_person',
            'date' => '2030-10-07',
            'timer' => ['15:00'],
        ], $this->user->id, 'UTC');

        $this->assertSame('in_person', Agenda::query()->where('user_id', $this->user->id)->value('type'));
    }

    public function testItStoreOnlineKeepsManualRoomLink(): void
    {
        $this->connectGoogle();

        $this->store(['room_link' => 'https://zoom.us/j/123']);

        $this->assertSame(['https://zoom.us/j/123'], $this->roomLinks());
        $this->assertFalse(VideoRoom::query()->where('session_id', (string) Agenda::query()->where('user_id', $this->user->id)->value('id'))->exists());
    }

    private function connectGoogle(GoogleCalendarStatusEnum $status = GoogleCalendarStatusEnum::INTEGRATED): void
    {
        GoogleCalendarAccount::query()->create([
            'user_id' => $this->user->id,
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
            'status' => $status->value,
        ]);
    }

    public function testItStoreOnlineWithGoogleConnectedUsesPsi4RoomAndDispatchesJob(): void
    {
        $this->connectGoogle();

        $this->store();

        // Com a sala do PSI4 já gravada, o link vai como local do evento no Google
        $this->assertJitsiLink($this->roomLinks()[0]);
        Bus::assertDispatched(SyncAgendaToGoogleJob::class);
    }

    public function testItCancelRemovesEventFromGoogle(): void
    {
        $this->connectGoogle();
        $this->store();
        $agenda = Agenda::query()->where('user_id', $this->user->id)->first();
        $agenda->update(['google_event_id' => 'psi4abc', 'google_synced_at' => now()]);

        $this->service->cancel($agenda->id, $this->user->id);

        Bus::assertDispatched(DeleteGoogleEventJob::class, fn ($job) => $job->eventId === 'psi4abc');
        $this->assertNull($agenda->fresh()->google_event_id);
    }

    public function testItStoreReusesCanceledSlotAndSyncsAgainToGoogle(): void
    {
        $this->connectGoogle();
        $this->store();
        $agenda = Agenda::query()->where('user_id', $this->user->id)->first();
        $this->service->cancel($agenda->id, $this->user->id);
        Bus::assertDispatchedTimes(SyncAgendaToGoogleJob::class, 1);

        $this->store();

        Bus::assertDispatchedTimes(SyncAgendaToGoogleJob::class, 2);
    }
}
