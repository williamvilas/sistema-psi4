<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AttendanceModeEnum;
use App\Helpers\CacheHelper;
use App\Models\AgendaHolidays;
use App\Models\User;
use App\Services\AgendaSettingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AgendaSettingServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AgendaSettingService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AgendaSettingService::class);
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'consultation_time' => '00:50',
            'available_time_minutes' => '00:10',
            'agenda_days' => ['seg' => ['enabled' => '1', 'start' => '08:00', 'end' => '12:00']],
            'lunch_enabled' => true,
            'lunch_start' => '12:00',
            'lunch_end' => '13:00',
            'holidays' => [
                ['date' => '2026-12-25', 'name' => 'Natal', 'work_enabled' => false, 'start' => null, 'end' => null],
                ['date' => '2026-12-31', 'name' => 'Réveillon', 'work_enabled' => true, 'start' => '08:00', 'end' => '12:00'],
            ],
        ], $override);
    }

    public function testItDetailsReturnsNullWhenUserHasNoSettings(): void
    {
        $this->assertNull($this->service->details($this->user->id));
    }

    public function testItStoreSavesSettingsAndHolidays(): void
    {
        $settings = $this->service->store($this->payload(), $this->user->id);

        $this->assertSame($this->user->id, $settings->user_id);
        $this->assertSame('00:50:00', $settings->fresh()->consultation_time);
        $this->assertSame(2, AgendaHolidays::where('user_id', $this->user->id)->count());
    }

    public function testItStoreSavesOnlineAttendanceModeByDefault(): void
    {
        $this->service->store($this->payload(), $this->user->id);

        $this->assertSame(AttendanceModeEnum::ONLINE, $this->service->details($this->user->id)->attendance_mode);
    }

    public function testItStoreSavesAttendanceModeAndDayPeriods(): void
    {
        $this->service->store($this->payload([
            'attendance_mode' => 'both',
            'agenda_days' => [
                'seg' => ['enabled' => '1', 'start' => '08:00', 'end' => '18:00', 'periods' => [
                    ['start' => '08:00', 'end' => '12:00', 'mode' => 'in_person'],
                    ['start' => '14:00', 'end' => '18:00', 'mode' => 'online'],
                ]],
                'ter' => ['enabled' => '1', 'start' => '08:00', 'end' => '12:00'],
            ],
        ]), $this->user->id);

        $saved = $this->service->details($this->user->id);

        $this->assertSame(AttendanceModeEnum::BOTH, $saved->attendance_mode);
        $this->assertSame([
            ['start' => '08:00', 'end' => '12:00', 'mode' => AttendanceModeEnum::IN_PERSON],
            ['start' => '14:00', 'end' => '18:00', 'mode' => AttendanceModeEnum::ONLINE],
        ], $saved->periodsForDay('seg'));
        $this->assertSame([['start' => '08:00', 'end' => '12:00', 'mode' => AttendanceModeEnum::BOTH]], $saved->periodsForDay('ter'));
    }

    public function testItPeriodsForDayIgnoresPeriodModeWhenGeneralModeIsNotBoth(): void
    {
        $this->service->store($this->payload([
            'attendance_mode' => 'online',
            'agenda_days' => ['seg' => ['enabled' => '1', 'periods' => [['start' => '08:00', 'end' => '12:00', 'mode' => 'in_person']]]],
        ]), $this->user->id);

        $this->assertSame(AttendanceModeEnum::ONLINE, $this->service->details($this->user->id)->periodsForDay('seg')[0]['mode']);
    }

    public function testItPeriodsForDayReturnsEmptyWhenDayHasNoHours(): void
    {
        $this->service->store($this->payload(['agenda_days' => ['seg' => ['enabled' => '0']]]), $this->user->id);

        $this->assertSame([], $this->service->details($this->user->id)->periodsForDay('seg'));
    }

    public function testItStoreSavesDinnerSettings(): void
    {
        $this->service->store($this->payload([
            'dinner_enabled' => true,
            'dinner_start' => '19:00',
            'dinner_end' => '20:00',
        ]), $this->user->id);

        $saved = $this->service->details($this->user->id);

        $this->assertTrue($saved->dinner_enabled);
        $this->assertSame('19:00:00', $saved->dinner_start);
        $this->assertSame('20:00:00', $saved->dinner_end);
    }

    public function testItStoreReplacesHolidaysOnSecondSave(): void
    {
        $this->service->store($this->payload(), $this->user->id);
        $this->service->store($this->payload(['holidays' => []]), $this->user->id);

        $this->assertSame(0, AgendaHolidays::where('user_id', $this->user->id)->count());
    }

    public function testItStorePreservesPastHolidays(): void
    {
        $this->service->store($this->payload(['holidays' => []]), $this->user->id);
        AgendaHolidays::create(['user_id' => $this->user->id, 'date' => now()->subDays(10)->toDateString(), 'name' => 'Passado']);
        AgendaHolidays::create(['user_id' => $this->user->id, 'date' => now()->addDays(10)->toDateString(), 'name' => 'Futuro']);

        $this->service->store($this->payload(['holidays' => []]), $this->user->id);

        $this->assertSame(['Passado'], AgendaHolidays::where('user_id', $this->user->id)->pluck('name')->all());
    }

    public function testItDetailsReturnsHolidaysAndRefreshesCacheAfterStore(): void
    {
        $this->service->store($this->payload(), $this->user->id);
        $this->assertCount(2, $this->service->details($this->user->id)->holidays);

        $this->service->store($this->payload(['holidays' => []]), $this->user->id);
        $this->assertCount(0, $this->service->details($this->user->id)->holidays);

        CacheHelper::forget("agenda_settings_user_{$this->user->id}");
    }

    public function testItStoreSavesSendLinkMessage(): void
    {
        $this->service->store($this->payload(['send_link_message' => 'Até breve!']), $this->user->id);

        $this->assertSame('Até breve!', $this->service->sendLinkText($this->user->id));
    }

    public function testItSendLinkTextReturnsDefaultWhenNotConfigured(): void
    {
        $this->assertSame(AgendaSettingService::DEFAULT_SEND_LINK_TEXT, $this->service->sendLinkText($this->user->id));

        $this->service->store($this->payload(['send_link_message' => '   ']), $this->user->id);

        $this->assertSame(AgendaSettingService::DEFAULT_SEND_LINK_TEXT, $this->service->sendLinkText($this->user->id));
    }

    public function testItBuildSendLinkMessageWithFirstNameLinkDateAndTime(): void
    {
        Carbon::setTestNow('2026-01-01 08:00:00');

        $message = $this->service->buildSendLinkMessage(
            'Até breve!',
            'WILLIAM vilas boas',
            Carbon::parse('2026-10-15 14:30:00'),
            'https://meet.google.com/abc',
        );

        $this->assertSame(
            "Oi William.\nAté breve!\n\n*Link:* https://meet.google.com/abc\n*Data:* 15/10/2026\n*Horário:* 14:30",
            $message,
        );
    }

    public function testItBuildSendLinkMessageExplainsCpfEntryForPsi4Room(): void
    {
        $message = $this->service->buildSendLinkMessage('Texto', 'Ana', Carbon::parse('2030-10-15 14:30:00'), config('psi_video.jitsi_base_url').'/abc123def456');

        $this->assertStringEndsWith("\n\n".__('video-room.enter_with_cpf'), $message);
    }

    public function testItBuildSendLinkMessageWithTodayAndTomorrowLabels(): void
    {
        $today = $this->service->buildSendLinkMessage('Texto', 'Ana', Carbon::now()->setTime(14, 30), 'https://x.com');
        $tomorrow = $this->service->buildSendLinkMessage('Texto', 'Ana', Carbon::tomorrow()->setTime(9, 0), 'https://x.com');

        $this->assertStringContainsString("*Data:* Hoje\n", $today);
        $this->assertStringContainsString("*Data:* Amanhã\n", $tomorrow);
    }
}
