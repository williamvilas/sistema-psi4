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

class AgendaServiceGoogleBusyTest extends TestCase
{
    use DatabaseTransactions;

    private const TZ = 'America/Sao_Paulo';

    private $google;

    private User $user;

    private AgendaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        AgendaSettings::query()->create([
            'user_id' => $this->user->id,
            'consultation_time' => '01:00:00',
            'available_time_minutes' => '00:00:00',
            // 2030-10-07 é segunda-feira
            'agenda_days' => ['seg' => ['enabled' => true, 'start' => '09:00', 'end' => '12:00']],
            'lunch_enabled' => false,
        ]);

        $settingService = new AgendaSettingService(new AgendaSettings, new AgendaHolidays);

        $this->google = Mockery::mock(GoogleCalendarService::class);
        $this->service = new AgendaService(new Agenda, $settingService, $this->google);
    }

    private function day(): array
    {
        $start = Carbon::parse('2030-10-07', self::TZ)->startOfDay();

        return [$start, $start->copy()->endOfDay()];
    }

    public function testItBusyKeysIgnoresGoogleWhenThereAreNoIntervals(): void
    {
        $this->google->shouldReceive('busyIntervals')->once()->andReturn([]);
        [$start, $end] = $this->day();

        $this->assertSame([], $this->service->busyKeys($this->user->id, self::TZ, $start, $end));
    }

    public function testItBusyKeysBlocksSlotsOverlappingGoogleIntervals(): void
    {
        // 10:30–11:15 em São Paulo (UTC-3) = 13:30–14:15 UTC: pega os slots 10:00 e 11:00, não o 09:00
        $this->google->shouldReceive('busyIntervals')->once()->andReturn([[
            'start' => Carbon::parse('2030-10-07 13:30:00', 'UTC'),
            'end' => Carbon::parse('2030-10-07 14:15:00', 'UTC'),
        ]]);
        [$start, $end] = $this->day();

        $keys = $this->service->busyKeys($this->user->id, self::TZ, $start, $end);

        $this->assertSame(['2030-10-07 10:00', '2030-10-07 11:00'], array_keys($keys));
    }

    public function testItFreeSlotsExcludesGoogleBusyTimes(): void
    {
        $this->google->shouldReceive('busyIntervals')->once()->andReturn([[
            'start' => Carbon::parse('2030-10-07 12:00:00', 'UTC'),
            'end' => Carbon::parse('2030-10-07 13:00:00', 'UTC'),
        ]]);
        [$start, $end] = $this->day();
        $busy = $this->service->busyKeys($this->user->id, self::TZ, $start, $end);

        $slots = $this->service->freeSlots($start, new AgendaSettings([
            'agenda_days' => ['seg' => ['enabled' => true, 'start' => '09:00', 'end' => '12:00']],
        ]), 60, $busy, Carbon::parse('2030-01-01', self::TZ));

        $this->assertSame(['10:00', '11:00'], $slots);
    }
}
