<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AgendaStatusEnum;
use App\Excel\Exports\AttendanceReportExport;
use App\Models\Agenda;
use App\Models\AgendaHolidays;
use App\Models\AgendaSettings;
use App\Models\User;
use App\Services\AgendaReportService;
use App\Services\AgendaService;
use App\Services\AgendaSettingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class AgendaReportServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TIMEZONE = 'America/Sao_Paulo';

    private $agenda;
    private $service;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agenda = Mockery::mock(Agenda::class);
        $this->service = $this->makeService();

        $this->user = User::factory()->create();
        Auth::login($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeService(): AgendaReportService
    {
        $settingService = new AgendaSettingService(new AgendaSettings(), new AgendaHolidays());

        return new AgendaReportService($this->agenda, new AgendaService(new Agenda(), $settingService, app(\App\Services\GoogleCalendarService::class)), $settingService);
    }

    private function row(array $attributes): Agenda
    {
        return new Agenda($attributes);
    }

    private function saveSettings(array $days, array $extra = []): void
    {
        AgendaSettings::query()->create(array_merge([
            'user_id' => $this->user->id,
            'consultation_time' => '01:00:00',
            'available_time_minutes' => '00:00:00',
            'agenda_days' => $days,
            'lunch_enabled' => false,
        ], $extra));
    }

    private function mockAttendance(array $counts, Collection $closed, array $top = [], float $lost = 0.0, array $previous = [], int $pending = 0, float $previousLost = 0.0): void
    {
        $this->agenda->shouldReceive('countByStatusBetween')->twice()->andReturn($counts, $previous);
        $this->agenda->shouldReceive('sumPriceByStatusBetween')->andReturn($lost, $previousLost);
        $this->agenda->shouldReceive('listByStatusesBetween')->once()->andReturn($closed);
        $this->agenda->shouldReceive('countPendingAttendance')->once()->andReturn($pending);
        $this->agenda->shouldReceive('topPatientsByNoShowBetween')->once()->andReturn(new Collection($top));
    }

    private function closedRows(array $noShowByDate): Collection
    {
        return new Collection(array_map(
            fn (string $date) => $this->row(['scheduled_on' => $date, 'status' => AgendaStatusEnum::NO_SHOW->value]),
            $noShowByDate,
        ));
    }

    public function testItAttendanceWithRatesAndLostRevenue(): void
    {
        // Arrange
        $this->mockAttendance(['completed' => 6, 'no_show' => 2, 'canceled' => 2, 'scheduled' => 5], new Collection(), lost: 300.0);

        // Act
        $report = $this->service->attendance($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-31');

        // Assert
        $this->assertSame(10, $report['total']);
        $this->assertSame(['rate' => 60.0, 'count' => 6, 'variation' => null], $report['attendance']);
        $this->assertSame(['rate' => 20.0, 'count' => 2, 'variation' => null], $report['no_show']);
        $this->assertSame(['rate' => 20.0, 'count' => 2, 'variation' => null], $report['canceled']);
        $this->assertSame(['total' => 300.0, 'count' => 2, 'variation' => null], $report['lost_revenue']);
        $this->assertSame(['start' => '01/10/2026', 'end' => '31/10/2026'], $report['period']);
        $this->assertSame(['start' => '31/08/2026', 'end' => '30/09/2026'], $report['previous_period']); // mesmos 31 dias
    }

    public function testItAttendanceComparesWithPreviousPeriod(): void
    {
        // Arrange: faltas sobem de 10% para 20% e a perda dobra
        $this->mockAttendance(
            ['completed' => 8, 'no_show' => 2],
            new Collection(),
            lost: 200.0,
            previous: ['completed' => 9, 'no_show' => 1],
            previousLost: 100.0,
        );

        // Act
        $report = $this->service->attendance($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-31');

        // Assert
        $this->assertSame(10.0, $report['no_show']['variation']);
        $this->assertSame(-10.0, $report['attendance']['variation']);
        $this->assertSame(0.0, $report['canceled']['variation']);
        $this->assertSame(100.0, $report['lost_revenue']['variation']);
    }

    public function testItAttendanceSkipsLostRevenueQueryWithoutNoShows(): void
    {
        // Arrange
        $this->mockAttendance(['completed' => 4], new Collection());
        $this->agenda->shouldNotReceive('sumPriceByStatusBetween');

        // Act
        $report = $this->service->attendance($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-31');

        // Assert
        $this->assertSame(0.0, $report['lost_revenue']['total']);
        $this->assertSame(100.0, $report['attendance']['rate']);
    }

    public function testItAttendanceCountsPendingSessions(): void
    {
        // Arrange
        $this->mockAttendance(['completed' => 1], new Collection(), pending: 4);

        // Act
        $report = $this->service->attendance($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-31');

        // Assert
        $this->assertSame(4, $report['pending']);
    }

    public function testItAttendanceWithoutAppointments(): void
    {
        // Arrange
        $this->mockAttendance([], new Collection());

        // Act
        $report = $this->service->attendance($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-31');

        // Assert
        $this->assertSame(0, $report['total']);
        $this->assertSame(0.0, $report['no_show']['rate']);
        $this->assertSame([], $report['top_patients']);
        $this->assertNull($report['insight']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 0], $report['chart']['no_show']);
    }

    public function testItAttendanceReturnsTopPatientsWithRateAndAttention(): void
    {
        // Arrange
        $top = [
            (object) ['patient_id' => 7, 'patient' => (object) ['name' => 'Ana'], 'no_show' => '2', 'canceled' => '1', 'total' => '4'],
            (object) ['patient_id' => 8, 'patient' => (object) ['name' => 'Bia'], 'no_show' => '1', 'canceled' => '0', 'total' => '1'],
            (object) ['patient_id' => 9, 'patient' => null, 'no_show' => '2', 'canceled' => '0', 'total' => '5'],
        ];
        $this->mockAttendance(['completed' => 5, 'no_show' => 5, 'canceled' => 1], new Collection(), $top);

        // Act
        $report = $this->service->attendance($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-31');

        // Assert
        $this->assertSame(
            ['id' => 7, 'name' => 'Ana', 'no_show' => 2, 'canceled' => 1, 'total' => 4, 'rate' => 50.0, 'attention' => true],
            $report['top_patients'][0],
        );
        $this->assertFalse($report['top_patients'][1]['attention']); // 100%, mas só 1 falta
        $this->assertFalse($report['top_patients'][2]['attention']); // 2 faltas, mas 40%
        $this->assertNull($report['top_patients'][2]['name']);
    }

    public function testItAttendanceBuildsInsightForSingleWeekday(): void
    {
        // Arrange: 2 faltas na segunda e 1 na sexta (horário de São Paulo)
        $closed = $this->closedRows(['2026-10-05 15:00:00', '2026-10-12 15:00:00', '2026-10-09 15:00:00']);
        $this->mockAttendance(['no_show' => 3], $closed);

        // Act
        $report = $this->service->attendance($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-31');

        // Assert
        $this->assertSame('Segunda-feira concentra 67% das faltas do período.', $report['insight']);
    }

    public function testItAttendanceBuildsInsightForTwoTiedWeekdays(): void
    {
        // Arrange: 2 faltas na segunda, 2 na sexta e 1 na quarta
        $closed = $this->closedRows(['2026-10-05 15:00:00', '2026-10-12 15:00:00', '2026-10-09 15:00:00', '2026-10-16 15:00:00', '2026-10-07 15:00:00']);
        $this->mockAttendance(['no_show' => 5], $closed);

        // Act
        $report = $this->service->attendance($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-31');

        // Assert
        $this->assertSame('Segunda-feira e Sexta-feira concentram 80% das faltas do período.', $report['insight']);
    }

    public function testItAttendanceSkipsInsightWhenAbsencesAreSpread(): void
    {
        // Arrange: uma falta em cada um de três dias diferentes
        $closed = $this->closedRows(['2026-10-05 15:00:00', '2026-10-07 15:00:00', '2026-10-09 15:00:00']);
        $this->mockAttendance(['no_show' => 3], $closed);

        // Act
        $report = $this->service->attendance($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-31');

        // Assert
        $this->assertNull($report['insight']);
    }

    public function testItAttendanceSkipsInsightWithFewAbsences(): void
    {
        // Arrange
        $closed = $this->closedRows(['2026-10-05 15:00:00', '2026-10-12 15:00:00']);
        $this->mockAttendance(['no_show' => 2], $closed);

        // Act
        $report = $this->service->attendance($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-31');

        // Assert
        $this->assertNull($report['insight']);
    }

    public function testItAttendanceChartGroupsByWeekdayInUserTimezone(): void
    {
        // Arrange: terça 01:00 UTC = segunda 22:00 em São Paulo
        $closed = new Collection([
            $this->row(['scheduled_on' => '2026-10-06 01:00:00', 'status' => AgendaStatusEnum::NO_SHOW->value]),
            $this->row(['scheduled_on' => '2026-10-13 01:00:00', 'status' => AgendaStatusEnum::NO_SHOW->value]),
            $this->row(['scheduled_on' => '2026-10-20 01:00:00', 'status' => AgendaStatusEnum::CANCELED->value]),
            $this->row(['scheduled_on' => '2026-10-26 21:00:00', 'status' => AgendaStatusEnum::COMPLETED->value]),
            $this->row(['scheduled_on' => '2026-10-06 12:00:00', 'status' => AgendaStatusEnum::COMPLETED->value]),
        ]);
        $this->mockAttendance(['completed' => 2, 'no_show' => 2, 'canceled' => 1], $closed);

        // Act
        $report = $this->service->attendance($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-31');

        // Assert
        $this->assertSame([0, 2, 0, 0, 0, 0, 0], $report['chart']['no_show']);
        $this->assertSame([0, 1, 0, 0, 0, 0, 0], $report['chart']['canceled']);
    }

    /** Sessão de 1h a partir do horário local de São Paulo (UTC-3). */
    private function booking(string $localStart, string $status = 'scheduled'): Agenda
    {
        $start = Carbon::parse($localStart, self::TIMEZONE)->utc();

        return $this->row(['scheduled_on' => $start->toDateTimeString(), 'ends_at' => $start->copy()->addHour()->toDateTimeString(), 'status' => $status]);
    }

    private function mockOccupancy(array $current, array $previous = []): void
    {
        $this->agenda->shouldReceive('listByStatusesBetween')->twice()->andReturn(new Collection($current), new Collection($previous));
    }

    public function testItOccupancyWithBookedAndFreeHours(): void
    {
        // Arrange: segundas das 09h às 12h = 3 horários de 1h; 2 reservados, agora é segunda 10h30
        Carbon::setTestNow(Carbon::parse('2026-10-05 10:30', self::TIMEZONE)->utc());
        $this->saveSettings(['seg' => ['enabled' => true, 'start' => '09:00', 'end' => '12:00']]);
        $this->mockOccupancy([
            $this->booking('2026-10-05 09:00', AgendaStatusEnum::COMPLETED->value),
            $this->booking('2026-10-05 10:00', AgendaStatusEnum::NO_SHOW->value),
        ]);

        // Act
        $report = $this->service->occupancy($this->user->id, self::TIMEZONE, '2026-10-05', '2026-10-11');

        // Assert
        $this->assertTrue($report['configured']);
        $this->assertSame(2.0, $report['booked_hours']);
        $this->assertSame(3.0, $report['capacity_hours']);
        $this->assertSame(1.0, $report['free_hours']);
        $this->assertSame(66.7, $report['occupancy_rate']);
        $this->assertSame(2, $report['sessions']);
        $this->assertSame(['completed' => 1, 'upcoming' => 0, 'no_show' => 1], $report['sessions_breakdown']);
        $this->assertSame(1, $report['free_slots_upcoming']);
        $this->assertSame(0, $report['idle_slots_past']);
        $this->assertSame(0, $report['outside_hours']);
        $this->assertSame(['05/10 – 11/10'], $report['chart']['categories']);
        $this->assertSame([2.0], $report['chart']['booked_hours']);
        $this->assertSame([3.0], $report['chart']['capacity_hours']);
        $this->assertSame([66.7], $report['chart']['rate']);
        $this->assertEquals([['label' => 'Segunda-feira', 'busy' => 2, 'total' => 3, 'rate' => 66.7]], $report['by_weekday']);
        $this->assertSame([9, 10, 11], $report['heatmap']['hours']);
        $this->assertSame('Seg', $report['heatmap']['rows'][0]['label']);
        $this->assertEquals(['busy' => 0, 'total' => 1, 'rate' => 0.0], $report['heatmap']['rows'][0]['cells'][2]);
    }

    public function testItOccupancyComparesWithPreviousPeriod(): void
    {
        // Arrange: período anterior (28/09 a 04/10) teve 1 de 3 horários ocupados
        $this->saveSettings(['seg' => ['enabled' => true, 'start' => '09:00', 'end' => '12:00']]);
        $this->mockOccupancy(
            [$this->booking('2026-10-05 09:00'), $this->booking('2026-10-05 10:00')],
            [$this->booking('2026-09-28 09:00')],
        );

        // Act
        $report = $this->service->occupancy($this->user->id, self::TIMEZONE, '2026-10-05', '2026-10-11');

        // Assert
        $this->assertSame(33.4, $report['variation']);
        $this->assertSame(100.0, $report['booked_variation']);
        $this->assertSame(['start' => '28/09/2026', 'end' => '04/10/2026'], $report['previous_period']);
    }

    public function testItOccupancyWithoutPreviousSessionsHasNoBookedVariation(): void
    {
        // Arrange
        $this->saveSettings(['seg' => ['enabled' => true, 'start' => '09:00', 'end' => '12:00']]);
        $this->mockOccupancy([$this->booking('2026-10-05 09:00')]);

        // Act
        $report = $this->service->occupancy($this->user->id, self::TIMEZONE, '2026-10-05', '2026-10-11');

        // Assert
        $this->assertSame(33.3, $report['variation']);
        $this->assertNull($report['booked_variation']);
    }

    public function testItOccupancySplitsFreeSlotsBetweenPastAndUpcoming(): void
    {
        // Arrange: duas segundas com 2 horários cada; agora é quarta 07/10
        Carbon::setTestNow(Carbon::parse('2026-10-07 08:00', self::TIMEZONE)->utc());
        $this->saveSettings(['seg' => ['enabled' => true, 'start' => '09:00', 'end' => '11:00']]);
        $this->mockOccupancy([$this->booking('2026-10-05 09:00', AgendaStatusEnum::COMPLETED->value)]);

        // Act
        $report = $this->service->occupancy($this->user->id, self::TIMEZONE, '2026-10-05', '2026-10-18');

        // Assert
        $this->assertSame(1, $report['idle_slots_past']);
        $this->assertSame(2, $report['free_slots_upcoming']);
        $this->assertSame(['05/10 – 11/10', '12/10 – 18/10'], $report['chart']['categories']);
        $this->assertSame([50.0, 0.0], $report['chart']['rate']);
    }

    public function testItOccupancyClipsWeekLabelsToPeriod(): void
    {
        // Arrange
        $this->saveSettings(['seg' => ['enabled' => true, 'start' => '09:00', 'end' => '10:00']]);
        $this->mockOccupancy([]);

        // Act
        $report = $this->service->occupancy($this->user->id, self::TIMEZONE, '2026-10-07', '2026-10-13');

        // Assert: semana sem expediente no período tem taxa nula
        $this->assertSame(['07/10 – 11/10', '12/10 – 13/10'], $report['chart']['categories']);
        $this->assertSame([null, 0.0], $report['chart']['rate']);
    }

    public function testItOccupancyIgnoresDisabledDaysAndHolidays(): void
    {
        // Arrange: segunda 05/10 é feriado sem trabalho, então só a segunda 12/10 conta
        $this->saveSettings(['seg' => ['enabled' => true, 'start' => '09:00', 'end' => '11:00']]);
        AgendaHolidays::query()->create(['user_id' => $this->user->id, 'date' => '2026-10-05', 'work_enabled' => false]);
        $this->mockOccupancy([]);

        // Act
        $report = $this->service->occupancy($this->user->id, self::TIMEZONE, '2026-10-05', '2026-10-18');

        // Assert
        $this->assertSame(0.0, $report['booked_hours']);
        $this->assertSame(2.0, $report['capacity_hours']);
        $this->assertSame(0.0, $report['occupancy_rate']);
    }

    public function testItOccupancyWithoutAgendaSettings(): void
    {
        // Arrange
        $this->mockOccupancy([]);

        // Act
        $report = $this->service->occupancy($this->user->id, self::TIMEZONE, '2026-10-05', '2026-10-11');

        // Assert
        $this->assertFalse($report['configured']);
        $this->assertNull($report['occupancy_rate']);
        $this->assertNull($report['variation']);
        $this->assertSame([], $report['heatmap']['rows']);
        $this->assertSame([], $report['by_weekday']);
        $this->assertNull($report['insight']);
    }

    public function testItOccupancyCapsRateAndCountsSessionsOutsideHours(): void
    {
        // Arrange: segunda das 09h às 10h; uma sessão às 19h, fora do horário configurado
        $this->saveSettings(['seg' => ['enabled' => true, 'start' => '09:00', 'end' => '10:00']]);
        $this->mockOccupancy([
            $this->booking('2026-10-05 09:00'),
            $this->booking('2026-10-05 19:00'),
        ]);

        // Act
        $report = $this->service->occupancy($this->user->id, self::TIMEZONE, '2026-10-05', '2026-10-11');

        // Assert
        $this->assertSame(100.0, $report['occupancy_rate']);
        $this->assertSame(0.0, $report['free_hours']);
        $this->assertSame(1, $report['outside_hours']);
    }

    public function testItOccupancyUsesSessionLengthWhenEndIsMissing(): void
    {
        // Arrange
        $this->saveSettings(['seg' => ['enabled' => true, 'start' => '09:00', 'end' => '11:00']]);
        $this->mockOccupancy([$this->row(['scheduled_on' => '2026-10-05 12:00:00', 'ends_at' => null, 'status' => 'confirmed'])]);

        // Act
        $report = $this->service->occupancy($this->user->id, self::TIMEZONE, '2026-10-05', '2026-10-11');

        // Assert
        $this->assertSame(1.0, $report['booked_hours']);
        $this->assertSame(1, $report['sessions_breakdown']['upcoming']);
    }

    public function testItOccupancyHighlightsTheLeastBusyShift(): void
    {
        // Arrange: manhã (08h-12h) com 3 de 4 ocupados e tarde (13h-17h) vazia, com almoço no meio
        $this->saveSettings(['seg' => ['enabled' => true, 'start' => '08:00', 'end' => '17:00']], ['lunch_enabled' => true, 'lunch_start' => '12:00', 'lunch_end' => '13:00']);
        $this->mockOccupancy([
            $this->booking('2026-10-05 08:00'),
            $this->booking('2026-10-05 09:00'),
            $this->booking('2026-10-05 10:00'),
        ]);

        // Act
        $report = $this->service->occupancy($this->user->id, self::TIMEZONE, '2026-10-05', '2026-10-11');

        // Assert
        $this->assertSame('Segunda-feira à tarde é o turno com mais horários livres (0% ocupado).', $report['insight']);
        $this->assertSame([8, 9, 10, 11, 13, 14, 15, 16], $report['heatmap']['hours']);
    }

    public function testItOccupancyWithoutInsightWhenThereAreNoSessions(): void
    {
        // Arrange
        $this->saveSettings(['seg' => ['enabled' => true, 'start' => '08:00', 'end' => '17:00']], ['lunch_enabled' => true, 'lunch_start' => '12:00', 'lunch_end' => '13:00']);
        $this->mockOccupancy([]);

        // Act
        $report = $this->service->occupancy($this->user->id, self::TIMEZONE, '2026-10-05', '2026-10-11');

        // Assert
        $this->assertNull($report['insight']);
    }

    public function testItOccupancyWithoutInsightWhenShiftsAreTooSmall(): void
    {
        // Arrange: só 2 horários na manhã e 2 na tarde, abaixo do mínimo para comparar
        $this->saveSettings(['seg' => ['enabled' => true, 'start' => '10:00', 'end' => '14:00']]);
        $this->mockOccupancy([$this->booking('2026-10-05 10:00')]);

        // Act
        $report = $this->service->occupancy($this->user->id, self::TIMEZONE, '2026-10-05', '2026-10-11');

        // Assert
        $this->assertNull($report['insight']);
    }

    public function testItAttendanceExportReturnsExport(): void
    {
        // Act
        $export = $this->service->attendanceExport($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-31');

        // Assert
        $this->assertInstanceOf(AttendanceReportExport::class, $export);
    }
}
