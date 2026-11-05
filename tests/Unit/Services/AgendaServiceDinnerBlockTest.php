<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AgendaSettings;
use App\Services\AgendaService;
use Carbon\Carbon;
use Tests\TestCase;

class AgendaServiceDinnerBlockTest extends TestCase
{
    private function settings(array $override = []): AgendaSettings
    {
        return new AgendaSettings(array_merge([
            'agenda_days' => ['seg' => ['enabled' => '1', 'start' => '17:00', 'end' => '22:00']],
            'lunch_enabled' => false,
            'dinner_enabled' => true,
            'dinner_start' => '19:00',
            'dinner_end' => '20:00',
        ], $override));
    }

    private function slots(AgendaSettings $settings): array
    {
        $monday = Carbon::parse('2026-10-12', 'America/Sao_Paulo');

        return app(AgendaService::class)->workingSlots($monday, $settings, 60);
    }

    public function testItBlocksDinnerInterval(): void
    {
        $this->assertSame(['17:00', '18:00', '20:00', '21:00'], $this->slots($this->settings()));
    }

    public function testItKeepsAllSlotsWhenDinnerDisabled(): void
    {
        $this->assertSame(
            ['17:00', '18:00', '19:00', '20:00', '21:00'],
            $this->slots($this->settings(['dinner_enabled' => false]))
        );
    }

    public function testItBlocksSlotsCrossingDinnerBoundary(): void
    {
        $slots = app(AgendaService::class)->workingSlots(
            Carbon::parse('2026-10-12', 'America/Sao_Paulo'),
            $this->settings(['agenda_days' => ['seg' => ['enabled' => '1', 'start' => '17:30', 'end' => '21:30']]]),
            60
        );

        // Após o bloco da janta a grade retoma às 20:00 (fim do bloco)
        $this->assertSame(['17:30', '20:00'], $slots);
    }

    public function testItBlocksLunchAndDinnerTogether(): void
    {
        $slots = $this->slots($this->settings([
            'agenda_days' => ['seg' => ['enabled' => '1', 'start' => '11:00', 'end' => '21:00']],
            'lunch_enabled' => true,
            'lunch_start' => '12:00',
            'lunch_end' => '13:00',
        ]));

        $this->assertNotContains('12:00', $slots);
        $this->assertNotContains('19:00', $slots);
        $this->assertContains('11:00', $slots);
        $this->assertContains('13:00', $slots);
    }
}
