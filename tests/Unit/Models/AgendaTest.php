<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\AgendaStatusEnum;
use App\Models\Agenda;
use Carbon\Carbon;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-16 15:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeAgenda(AgendaStatusEnum $status, string $start, string $end): Agenda
    {
        return (new Agenda)->forceFill([
            'status' => $status->value,
            'scheduled_on' => Carbon::parse($start, 'UTC'),
            'ends_at' => Carbon::parse($end, 'UTC'),
        ]);
    }

    public function testItIsOpenWhenScheduledInTheFuture(): void
    {
        $agenda = $this->makeAgenda(AgendaStatusEnum::SCHEDULED, '2026-10-16 18:00', '2026-10-16 18:50');

        $this->assertTrue($agenda->isOpen());
        $this->assertFalse($agenda->isPendingAttendance());
        $this->assertFalse($agenda->canMarkAttendance());
        $this->assertFalse($agenda->isInProgress());
    }

    public function testItIsPendingAttendanceWhenOpenSessionAlreadyEnded(): void
    {
        $agenda = $this->makeAgenda(AgendaStatusEnum::CONFIRMED, '2026-10-16 13:00', '2026-10-16 13:50');

        $this->assertFalse($agenda->isOpen());
        $this->assertTrue($agenda->isPendingAttendance());
        $this->assertTrue($agenda->canMarkAttendance());
        $this->assertNull($agenda->startsInMinutes());
    }

    public function testItIsInProgressBetweenStartAndEnd(): void
    {
        $agenda = $this->makeAgenda(AgendaStatusEnum::SCHEDULED, '2026-10-16 14:30', '2026-10-16 15:20');

        $this->assertTrue($agenda->isInProgress());
        $this->assertNull($agenda->startsInMinutes());
    }

    public function testItReturnsMinutesToStartWhenSessionIsClose(): void
    {
        $agenda = $this->makeAgenda(AgendaStatusEnum::SCHEDULED, '2026-10-16 15:10', '2026-10-16 16:00');

        $this->assertSame(10, $agenda->startsInMinutes());
    }

    public function testItReturnsNullMinutesWhenSessionIsFarAway(): void
    {
        $agenda = $this->makeAgenda(AgendaStatusEnum::SCHEDULED, '2026-10-16 16:00', '2026-10-16 16:50');

        $this->assertNull($agenda->startsInMinutes());
    }

    public function testItAllowsChangingAttendanceWhenAlreadyFinished(): void
    {
        foreach ([AgendaStatusEnum::COMPLETED, AgendaStatusEnum::NO_SHOW] as $status) {
            $agenda = $this->makeAgenda($status, '2026-10-16 13:00', '2026-10-16 13:50');

            $this->assertTrue($agenda->canMarkAttendance());
            $this->assertFalse($agenda->isOpen());
        }
    }

    public function testItIsClosedWhenCanceled(): void
    {
        $agenda = $this->makeAgenda(AgendaStatusEnum::CANCELED, '2026-10-16 18:00', '2026-10-16 18:50');

        $this->assertFalse($agenda->isOpen());
        $this->assertFalse($agenda->isPendingAttendance());
        $this->assertFalse($agenda->canMarkAttendance());
        $this->assertNull($agenda->startsInMinutes());
    }

    public function testItMapsEachStatusToADistinctTone(): void
    {
        $tones = array_map(fn (AgendaStatusEnum $status) => $status->tone(), AgendaStatusEnum::cases());

        $this->assertSame($tones, array_unique($tones));
        $this->assertSame('info', AgendaStatusEnum::CONFIRMED->tone());
        $this->assertSame('warning', AgendaStatusEnum::SCHEDULED->tone());
    }
}
