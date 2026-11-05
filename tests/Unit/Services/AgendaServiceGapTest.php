<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AgendaStatusEnum;
use App\Models\Agenda;
use App\Models\AgendaHolidays;
use App\Models\AgendaSettings;
use App\Models\Patients;
use App\Models\User;
use App\Services\AgendaService;
use App\Services\AgendaSettingService;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class AgendaServiceGapTest extends TestCase
{
    use DatabaseTransactions;

    private const TZ = 'America/Sao_Paulo';

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

    private function settings(string $gap, array $override = []): AgendaSettings
    {
        return new AgendaSettings(array_merge([
            'consultation_time' => '01:00:00',
            'available_time_minutes' => $gap,
            // 2030-10-07 é segunda-feira
            'agenda_days' => ['seg' => ['enabled' => true, 'start' => '08:00', 'end' => '12:00']],
            'lunch_enabled' => false,
        ], $override));
    }

    private function slots(AgendaSettings $settings): array
    {
        return $this->service->workingSlots(Carbon::parse('2030-10-07', self::TZ), $settings, 60);
    }

    public function testNoGapKeepsTheContiguousGrid(): void
    {
        $this->assertSame(['08:00', '09:00', '10:00', '11:00'], $this->slots($this->settings('00:00:00')));
    }

    public function testNextSlotIsStartPlusDurationPlusGap(): void
    {
        $this->assertSame(['08:00', '09:30', '11:00'], $this->slots($this->settings('00:30:00')));
    }

    public function testLastSlotDoesNotNeedTheGapAfterIt(): void
    {
        $settings = $this->settings('00:30:00', ['agenda_days' => ['seg' => ['enabled' => true, 'start' => '08:00', 'end' => '11:00']]]);

        $this->assertSame(['08:00', '09:30'], $this->slots($settings));
    }

    public function testGridResumesAtTheEndOfLunch(): void
    {
        $settings = $this->settings('00:30:00', [
            'agenda_days' => ['seg' => ['enabled' => true, 'start' => '08:00', 'end' => '16:00']],
            'lunch_enabled' => true,
            'lunch_start' => '12:00',
            'lunch_end' => '13:00',
        ]);

        $this->assertSame(['08:00', '09:30', '11:00', '13:00', '14:30'], $this->slots($settings));
    }

    public function testGapDefaultsToZeroWithoutSetting(): void
    {
        $this->assertSame(0, $this->service->gapMinutes(null));
        $this->assertSame(0, $this->service->gapMinutes(new AgendaSettings));
        $this->assertSame(10, $this->service->gapMinutes($this->settings('00:10:00')));
    }

    public function testExistingAppointmentBlocksOverlappingAndAdjacentSlotsByGap(): void
    {
        AgendaSettings::query()->create([
            'user_id' => $this->user->id,
            'consultation_time' => '01:00:00',
            'available_time_minutes' => '00:30:00',
            'agenda_days' => ['seg' => ['enabled' => true, 'start' => '08:00', 'end' => '14:00']],
            'lunch_enabled' => false,
        ]);

        $patient = Patients::create(['user_id' => $this->user->id, 'name' => 'Paciente', 'phone' => '11911112222']);

        // Consulta de 09:45 às 10:45 (SP) feita com uma grade diferente da atual (ex.: antes de mudar a configuração)
        Agenda::query()->create([
            'user_id' => $this->user->id,
            'patient_id' => $patient->id,
            'type' => 'online',
            'scheduled_on' => Carbon::parse('2030-10-07 09:45', self::TZ)->utc(),
            'ends_at' => Carbon::parse('2030-10-07 10:45', self::TZ)->utc(),
            'status' => AgendaStatusEnum::SCHEDULED->value,
        ]);

        $start = Carbon::parse('2030-10-07', self::TZ)->startOfDay();
        $busy = $this->service->busyKeys($this->user->id, self::TZ, $start, $start->copy()->endOfDay());
        $free = $this->service->freeSlots($start, (new AgendaSettingService(new AgendaSettings, new AgendaHolidays))->details($this->user->id), 60, $busy, Carbon::parse('2030-01-01', self::TZ));

        // Grade: 08:00, 09:30, 11:00, 12:30. 09:30 sobrepõe e 11:00 fica a 15 min da consulta (< folga de 30)
        $this->assertNotContains('09:30', $free);
        $this->assertNotContains('11:00', $free);
        $this->assertContains('12:30', $free);
    }
}
