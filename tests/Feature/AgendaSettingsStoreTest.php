<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AgendaSettings;
use App\Models\PsychologistSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AgendaSettingsStoreTest extends TestCase
{
    use DatabaseTransactions;

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'consultation_time' => '00:50',
            'available_time_minutes' => '00:10',
            'agenda_days' => [
                'seg' => ['enabled' => 1, 'start' => '08:00', 'end' => '18:00'],
                'sab' => ['start' => '18:00', 'end' => '08:00'],
            ],
            'lunch_enabled' => 1,
            'lunch_start' => '12:00',
            'lunch_end' => '13:00',
        ], $overrides);
    }

    /**
     * "Online e presencial" exige o endereço do consultório.
     */
    private function hybridUser(): User
    {
        $user = User::factory()->create(['current_plan' => 'professional']);
        PsychologistSettings::query()->create(['user_id' => $user->id, 'rua' => 'Rua das Flores', 'numero' => '100', 'cidade' => 'Campinas']);

        return $user;
    }

    private function hybridPayload(array $periods): array
    {
        return array_merge($this->payload(), [
            'attendance_mode' => 'both',
            'agenda_days' => ['seg' => ['enabled' => 1, 'periods' => $periods]],
        ]);
    }

    public function testItStoresDayPeriodsSortedWithDayRangeAndModes(): void
    {
        $user = $this->hybridUser();

        $this->actingAs($user)->postJson(route('settings.agenda.store'), $this->hybridPayload([
            '1700000000000' => ['start' => '14:00', 'end' => '19:00', 'mode' => 'online'],
            '0' => ['start' => '08:00', 'end' => '12:00', 'mode' => 'in_person'],
        ]))->assertOk();

        $monday = AgendaSettings::query()->where('user_id', $user->id)->first()->agenda_days['seg'];

        $this->assertSame('08:00', $monday['start']);
        $this->assertSame('19:00', $monday['end']);
        $this->assertEquals([
            ['start' => '08:00', 'end' => '12:00', 'mode' => 'in_person'],
            ['start' => '14:00', 'end' => '19:00', 'mode' => 'online'],
        ], $monday['periods']);
    }

    public function testItDropsPeriodModeWhenAttendanceIsNotBoth(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        $this->actingAs($user)->postJson(route('settings.agenda.store'), array_merge($this->hybridPayload([
            ['start' => '08:00', 'end' => '12:00', 'mode' => 'in_person'],
        ]), ['attendance_mode' => 'online']))->assertOk();

        $monday = AgendaSettings::query()->where('user_id', $user->id)->first()->agenda_days['seg'];

        $this->assertEquals([['start' => '08:00', 'end' => '12:00']], $monday['periods']);
    }

    public function testItRejectsOverlappingPeriods(): void
    {
        $this->actingAs($this->hybridUser())
            ->postJson(route('settings.agenda.store'), $this->hybridPayload([
                ['start' => '08:00', 'end' => '12:00', 'mode' => 'in_person'],
                ['start' => '11:00', 'end' => '15:00', 'mode' => 'online'],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agenda_days.seg.periods.1.start' => __('agenda.periods_overlap')]);
    }

    public function testItRejectsPeriodEndingBeforeStart(): void
    {
        $this->actingAs($this->hybridUser())
            ->postJson(route('settings.agenda.store'), $this->hybridPayload([
                ['start' => '08:00', 'end' => '12:00', 'mode' => 'in_person'],
                ['start' => '15:00', 'end' => '14:00', 'mode' => 'online'],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agenda_days.seg.periods.1.end' => __('agenda.end_before_start')]);
    }

    public function testItRejectsMoreThanThreePeriodsPerDay(): void
    {
        $this->actingAs($this->hybridUser())
            ->postJson(route('settings.agenda.store'), $this->hybridPayload([
                ['start' => '07:00', 'end' => '08:00'],
                ['start' => '09:00', 'end' => '10:00'],
                ['start' => '11:00', 'end' => '12:00'],
                ['start' => '13:00', 'end' => '14:00'],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agenda_days.seg.periods']);
    }

    public function testItStoresAgendaWhenDisabledDayHasInvertedHours(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        $this->actingAs($user)->postJson(route('settings.agenda.store'), $this->payload())->assertOk();
    }

    public function testItRejectsEnabledDayEndingBeforeStart(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        $this->actingAs($user)
            ->postJson(route('settings.agenda.store'), $this->payload(['agenda_days' => ['seg' => ['end' => '07:00']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agenda_days.seg.end' => __('agenda.end_before_start')]);
    }

    public function testItRejectsLunchEndingBeforeStart(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        $this->actingAs($user)
            ->postJson(route('settings.agenda.store'), $this->payload(['lunch_end' => '12:00']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lunch_end']);
    }

    public function testItRejectsWorkingHolidayEndingBeforeStart(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        $this->actingAs($user)
            ->postJson(route('settings.agenda.store'), $this->payload([
                'holidays' => [
                    '99' => ['date' => now()->addMonth()->format('d/m/Y'), 'work_enabled' => 1, 'start' => '12:00', 'end' => '08:00'],
                ],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['holidays.99.end']);
    }

    public function testItIgnoresHoursOfHolidayWithoutAttendance(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        $this->actingAs($user)
            ->postJson(route('settings.agenda.store'), $this->payload([
                'holidays' => [
                    '99' => ['date' => now()->addMonth()->format('d/m/Y'), 'start' => '12:00', 'end' => '08:00'],
                ],
            ]))
            ->assertOk();
    }
}
