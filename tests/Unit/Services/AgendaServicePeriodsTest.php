<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Agenda;
use App\Models\AgendaHolidays;
use App\Models\AgendaSettings;
use App\Models\User;
use App\Services\AgendaService;
use App\Services\AgendaSettingService;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class AgendaServicePeriodsTest extends TestCase
{
    use DatabaseTransactions;

    private const TZ = 'America/Sao_Paulo';

    // 2030-10-07 é segunda-feira
    private const MONDAY = '2030-10-07';

    private User $user;

    private AgendaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $google = Mockery::mock(GoogleCalendarService::class);
        $google->shouldReceive('busyIntervals')->andReturn([]);

        $this->service = new AgendaService(new Agenda, new AgendaSettingService(new AgendaSettings, new AgendaHolidays), $google);
    }

    private function settings(array $monday, string $mode = 'both', array $override = []): AgendaSettings
    {
        return new AgendaSettings(array_merge([
            'consultation_time' => '01:00:00',
            'available_time_minutes' => '00:00:00',
            'attendance_mode' => $mode,
            'agenda_days' => ['seg' => array_merge(['enabled' => true], $monday)],
            'lunch_enabled' => false,
        ], $override));
    }

    private function hybridMonday(): array
    {
        return ['periods' => [
            ['start' => '08:00', 'end' => '10:00', 'mode' => 'in_person'],
            ['start' => '14:00', 'end' => '16:00', 'mode' => 'online'],
        ]];
    }

    private function day(): Carbon
    {
        return Carbon::parse(self::MONDAY, self::TZ);
    }

    public function testItWorkingSlotTypesGeneratesSlotsPerPeriodWithItsMode(): void
    {
        $slots = $this->service->workingSlotTypes($this->day(), $this->settings($this->hybridMonday()), 60);

        $this->assertSame([
            '08:00' => ['in_person'],
            '09:00' => ['in_person'],
            '14:00' => ['online'],
            '15:00' => ['online'],
        ], $slots);
    }

    public function testItWorkingSlotsKeepsReturningOnlyTheTimes(): void
    {
        $slots = $this->service->workingSlots($this->day(), $this->settings($this->hybridMonday()), 60);

        $this->assertSame(['08:00', '09:00', '14:00', '15:00'], $slots);
    }

    public function testItWorkingSlotTypesSortsPeriodsSavedOutOfOrder(): void
    {
        $monday = ['periods' => array_reverse($this->hybridMonday()['periods'])];

        $slots = $this->service->workingSlots($this->day(), $this->settings($monday), 60);

        $this->assertSame(['08:00', '09:00', '14:00', '15:00'], $slots);
    }

    public function testItWorkingSlotTypesUsesBothTypesForPeriodWithoutMode(): void
    {
        $slots = $this->service->workingSlotTypes($this->day(), $this->settings(['periods' => [['start' => '08:00', 'end' => '09:00']]]), 60);

        $this->assertSame(['08:00' => ['online', 'in_person']], $slots);
    }

    public function testItWorkingSlotTypesIgnoresPeriodModeWhenGeneralModeIsNotBoth(): void
    {
        $slots = $this->service->workingSlotTypes($this->day(), $this->settings($this->hybridMonday(), 'online'), 60);

        $this->assertSame(['online'], array_values(array_unique(array_merge(...array_values($slots)))));
    }

    public function testItWorkingSlotTypesFallsBackToDayStartAndEndWithoutPeriods(): void
    {
        $slots = $this->service->workingSlotTypes($this->day(), $this->settings(['start' => '08:00', 'end' => '10:00'], 'in_person'), 60);

        $this->assertSame(['08:00' => ['in_person'], '09:00' => ['in_person']], $slots);
    }

    public function testItWorkingSlotTypesSkipsLunchInsideAPeriod(): void
    {
        $settings = $this->settings(['periods' => [['start' => '11:00', 'end' => '15:00', 'mode' => 'online']]], 'both', [
            'lunch_enabled' => true,
            'lunch_start' => '12:00',
            'lunch_end' => '13:00',
        ]);

        $this->assertSame(['11:00', '13:00', '14:00'], $this->service->workingSlots($this->day(), $settings, 60));
    }

    public function testItWorkingSlotTypesReturnsEmptyWhenDayIsDisabled(): void
    {
        $monday = array_merge($this->hybridMonday(), ['enabled' => false]);

        $this->assertSame([], $this->service->workingSlotTypes($this->day(), $this->settings($monday), 60));
    }

    public function testItWorkingSlotTypesLimitsPeriodsToHolidayHours(): void
    {
        $settings = $this->settings($this->hybridMonday());
        $settings->setRelation('holidays', collect([
            new AgendaHolidays(['date' => self::MONDAY, 'work_enabled' => true, 'start' => '09:00:00', 'end' => '15:00:00']),
        ]));

        $slots = $this->service->workingSlotTypes($this->day(), $settings, 60);

        $this->assertSame(['09:00' => ['in_person'], '14:00' => ['online']], $slots);
    }

    public function testItWorkingSlotTypesReplacesSinglePeriodWithHolidayHoursKeepingMode(): void
    {
        $settings = $this->settings(['periods' => [['start' => '08:00', 'end' => '10:00', 'mode' => 'in_person']]]);
        $settings->setRelation('holidays', collect([
            new AgendaHolidays(['date' => self::MONDAY, 'work_enabled' => true, 'start' => '13:00:00', 'end' => '15:00:00']),
        ]));

        $slots = $this->service->workingSlotTypes($this->day(), $settings, 60);

        $this->assertSame(['13:00' => ['in_person'], '14:00' => ['in_person']], $slots);
    }

    public function testItWorkingSlotTypesReturnsEmptyOnHolidayWithoutWork(): void
    {
        $settings = $this->settings($this->hybridMonday());
        $settings->setRelation('holidays', collect([new AgendaHolidays(['date' => self::MONDAY, 'work_enabled' => false])]));

        $this->assertSame([], $this->service->workingSlotTypes($this->day(), $settings, 60));
    }

    public function testItFreeSlotsFiltersByType(): void
    {
        $settings = $this->settings($this->hybridMonday());
        $minStart = Carbon::parse('2030-01-01', self::TZ);

        $this->assertSame(['08:00', '09:00'], $this->service->freeSlots($this->day(), $settings, 60, [], $minStart, 'in_person'));
        $this->assertSame(['14:00', '15:00'], $this->service->freeSlots($this->day(), $settings, 60, [], $minStart, 'online'));
        $this->assertSame(['08:00', '09:00', '14:00', '15:00'], $this->service->freeSlots($this->day(), $settings, 60, [], $minStart));
    }

    public function testItFreeSlotTypesRemovesBusyTimes(): void
    {
        $slots = $this->service->freeSlotTypes($this->day(), $this->settings($this->hybridMonday()), 60, [self::MONDAY.' 08:00' => 0], Carbon::parse('2030-01-01', self::TZ));

        $this->assertSame(['09:00', '14:00', '15:00'], array_keys($slots));
    }

    public function testItSlotsByTypeGroupsTimesKeepingOrder(): void
    {
        $byType = $this->service->slotsByType([
            '08:00' => ['in_person'],
            '09:00' => ['online', 'in_person'],
            '14:00' => ['online'],
        ]);

        $this->assertSame(['in_person' => ['08:00', '09:00'], 'online' => ['09:00', '14:00']], $byType);
    }

    public function testItTypesOfReturnsTypesInEnumOrder(): void
    {
        $this->assertSame(['online', 'in_person'], $this->service->typesOf(['08:00' => ['in_person'], '14:00' => ['online']]));
    }

    public function testItTypesOfReturnsOnlineWhenThereAreNoSlots(): void
    {
        $this->assertSame(['online'], $this->service->typesOf([]));
    }
}
