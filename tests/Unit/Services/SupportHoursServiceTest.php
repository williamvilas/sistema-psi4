<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SupportHoursService;
use Carbon\Carbon;
use Tests\TestCase;

class SupportHoursServiceTest extends TestCase
{
    private SupportHoursService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('info.support_hours', [
            'timezone' => 'America/Sao_Paulo',
            'days' => [
                1 => ['08:00', '20:00'],
                2 => ['08:00', '20:00'],
                3 => ['08:00', '20:00'],
                4 => ['08:00', '20:00'],
                5 => ['08:00', '20:00'],
                6 => ['08:00', '12:00'],
            ],
        ]);

        $this->service = new SupportHoursService;
    }

    private function at(string $datetime): Carbon
    {
        return Carbon::parse($datetime, 'America/Sao_Paulo');
    }

    public function testItIsOnlineDuringWeekdayHours(): void
    {
        // Act — quinta-feira, 15/10/2026 às 10h
        $status = $this->service->status($this->at('2026-10-15 10:00'));

        // Assert
        $this->assertTrue($status['online']);
        $this->assertSame(__('help.status.online'), $status['label']);
    }

    public function testItIsOnlineExactlyAtOpeningTime(): void
    {
        // Act
        $status = $this->service->status($this->at('2026-10-15 08:00'));

        // Assert
        $this->assertTrue($status['online']);
    }

    public function testItIsOfflineExactlyAtClosingTimeAndOpensTomorrow(): void
    {
        // Act
        $status = $this->service->status($this->at('2026-10-15 20:00'));

        // Assert
        $this->assertFalse($status['online']);
        $this->assertSame('Fora do horário · respondemos amanhã às 8h', $status['label']);
    }

    public function testItOpensTodayWhenBeforeOpeningTime(): void
    {
        // Act
        $status = $this->service->status($this->at('2026-10-15 06:30'));

        // Assert
        $this->assertFalse($status['online']);
        $this->assertSame('Fora do horário · respondemos hoje às 8h', $status['label']);
    }

    public function testItUsesSaturdayShortHours(): void
    {
        // Act — sábado, 17/10/2026
        $online = $this->service->status($this->at('2026-10-17 11:59'));
        $offline = $this->service->status($this->at('2026-10-17 12:00'));

        // Assert — domingo fechado, reabre na segunda
        $this->assertTrue($online['online']);
        $this->assertFalse($offline['online']);
        $this->assertSame('Fora do horário · respondemos na segunda-feira às 8h', $offline['label']);
    }

    public function testItIsOfflineOnSunday(): void
    {
        // Act — domingo, 18/10/2026
        $status = $this->service->status($this->at('2026-10-18 10:00'));

        // Assert
        $this->assertFalse($status['online']);
        $this->assertSame('Fora do horário · respondemos amanhã às 8h', $status['label']);
    }

    public function testItIsOfflineOnNationalHolidayAndSkipsIt(): void
    {
        // Act — segunda, 12/10/2026 (Nossa Senhora Aparecida); domingo 11/10 também fechado
        $holiday = $this->service->status($this->at('2026-10-12 10:00'));
        $dayBefore = $this->service->status($this->at('2026-10-11 10:00'));

        // Assert
        $this->assertFalse($holiday['online']);
        $this->assertSame('Fora do horário · respondemos amanhã às 8h', $holiday['label']);
        $this->assertSame('Fora do horário · respondemos na terça-feira às 8h', $dayBefore['label']);
    }

    public function testItConvertsUtcToSupportTimezone(): void
    {
        // Act — 22h UTC = 19h em São Paulo (quinta-feira)
        $status = $this->service->status(Carbon::parse('2026-10-15 22:00', 'UTC'));

        // Assert
        $this->assertTrue($status['online']);
    }

    public function testItFormatsHoursWithMinutes(): void
    {
        // Arrange
        config()->set('info.support_hours.days', [4 => ['08:30', '18:00']]);

        // Act
        $status = $this->service->status($this->at('2026-10-15 07:00'));

        // Assert
        $this->assertSame('Fora do horário · respondemos hoje às 8h30', $status['label']);
    }

    public function testItReturnsGenericLabelWhenNoDayIsOpen(): void
    {
        // Arrange
        config()->set('info.support_hours.days', []);

        // Act
        $status = $this->service->status($this->at('2026-10-15 10:00'));

        // Assert
        $this->assertFalse($status['online']);
        $this->assertSame(__('help.status.closed'), $status['label']);
    }

    public function testItGroupsScheduleByConsecutiveDays(): void
    {
        // Act
        $schedule = $this->service->schedule();

        // Assert
        $this->assertSame([
            ['days' => 'Segunda a sexta', 'hours' => '8h às 20h'],
            ['days' => 'Sábado', 'hours' => '8h às 12h'],
            ['days' => 'Domingo', 'hours' => 'Fechado'],
            ['days' => 'Feriados nacionais', 'hours' => 'Fechado'],
        ], $schedule);
    }
}
