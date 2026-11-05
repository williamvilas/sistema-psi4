<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AgendaStatusEnum;
use App\Helpers\DashboardCache;
use App\Models\Agenda;
use App\Models\AgendaHolidays;
use App\Models\AgendaSettings;
use App\Models\Patients;
use App\Models\PatientScaleApplications;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Services\AgendaReportService;
use App\Services\AgendaService;
use App\Services\AgendaSettingService;
use App\Services\DashboardService;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TIMEZONE = 'America/Sao_Paulo';

    private $agenda;
    private $patients;
    private $paymentOrder;
    private $scaleApplications;
    private $agendaReportService;
    private $service;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 29/12/2026 12:00 em São Paulo (em UTC para o Carbon não reinterpretar datas sem fuso)
        Carbon::setTestNow(Carbon::parse('2026-12-29 15:00:00', 'UTC'));

        $this->agenda = Mockery::mock(Agenda::class);
        $this->patients = Mockery::mock(Patients::class);
        $this->paymentOrder = Mockery::mock(PaymentOrder::class);
        $this->scaleApplications = Mockery::mock(PatientScaleApplications::class);
        // AgendaReportService é readonly (não mockável): usa o real sobre o model mockado, sem configuração de agenda
        $settingService = new AgendaSettingService(new AgendaSettings, new AgendaHolidays);
        $this->agendaReportService = new AgendaReportService($this->agenda, new AgendaService(new Agenda, $settingService, app(GoogleCalendarService::class)), $settingService);
        $this->service = new DashboardService($this->agenda, $this->patients, $this->paymentOrder, $this->scaleApplications, $this->agendaReportService);

        $this->user = User::factory()->create();
        Auth::login($this->user);
        DashboardCache::forget($this->user->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function mockQueries(array $overrides = []): void
    {
        $this->agenda->shouldReceive('countByStatusBetween')->andReturn(...($overrides['counts'] ?? [['scheduled' => 21, 'completed' => 3]]));
        $this->agenda->shouldReceive('countPendingAttendance')->andReturn(...($overrides['pending'] ?? [5]));
        $this->paymentOrder->shouldReceive('countPending')->with($this->user->id, '2026-12-29')->andReturn(7);
        $this->patients->shouldReceive('countByBirthdayDay')->with($this->user->id, '12-29')->andReturn(4);
        $this->agenda->shouldReceive('listNotCanceledBetweenWithPatient')->andReturn($overrides['today'] ?? new EloquentCollection());
        $this->paymentOrder->shouldReceive('summarizeByStatusAndPeriod')->andReturn(...($overrides['paid'] ?? [['total' => 0.0, 'count' => 0]]));
        // 3 status = gráfico semanal; 4 status = ocupação (sem sessões abertas no cenário)
        $this->agenda->shouldReceive('listByStatusesBetween')->andReturnUsing(fn ($userId, $statuses) => count($statuses) === 3 ? ($overrides['weekly'] ?? new EloquentCollection) : new EloquentCollection);
        $this->paymentOrder->shouldReceive('summarizeOverdue')->andReturn(['count' => 0, 'total' => 0.0]);
        $this->paymentOrder->shouldReceive('listOverdue')->andReturn(new EloquentCollection());
        $this->agenda->shouldReceive('listInactivePatients')->andReturn($overrides['inactive'] ?? collect());
        $this->agenda->shouldReceive('listClosedSince')->andReturn($overrides['closed'] ?? new EloquentCollection());
        $this->scaleApplications->shouldReceive('lastAppliedOnByPatient')->andReturn($overrides['last_scales'] ?? collect());
        $this->patients->shouldReceive('findByIds')->andReturn(new EloquentCollection(($overrides['patients'] ?? collect())->all()));
    }

    private function makeSession(int $patientId, AgendaStatusEnum $status, string $scheduledOn): Agenda
    {
        return (new Agenda)->forceFill(['patient_id' => $patientId, 'status' => $status, 'scheduled_on' => Carbon::parse($scheduledOn, 'UTC')]);
    }

    private function makePatient(int $id, string $name): Patients
    {
        return (new Patients)->forceFill(['id' => $id, 'name' => $name, 'phone' => '11999999999']);
    }

    public function testItReportBuildsCountersAndCachesResult(): void
    {
        // Arrange
        $this->mockQueries();

        // Act
        $report = $this->service->report($this->user->id, self::TIMEZONE);

        // Assert
        $this->assertSame(5, $report['pending_attendance']);
        $this->assertSame(7, $report['pending_payments']);
        $this->assertSame(21, $report['scheduled_today']);
        $this->assertSame(4, $report['birthdays_today']);
        $this->assertSame($report, DashboardCache::get($this->user->id));
    }

    public function testItReportReturnsCachedWithoutQueryingDatabase(): void
    {
        // Arrange
        $cached = ['pending_attendance' => 99];
        DashboardCache::put($this->user->id, $cached);
        $this->agenda->shouldNotReceive('countByStatusBetween');
        $this->paymentOrder->shouldNotReceive('countPending');

        // Act
        $report = $this->service->report($this->user->id, self::TIMEZONE);

        // Assert
        $this->assertSame($cached, $report);
    }

    public function testItReportQueriesAgainAfterCacheIsForgotten(): void
    {
        // Arrange
        $this->mockQueries(['counts' => [['scheduled' => 1], ['scheduled' => 1]], 'pending' => [1, 2]]);
        $this->service->report($this->user->id, self::TIMEZONE);

        // Act
        DashboardCache::forget($this->user->id);
        $report = $this->service->report($this->user->id, self::TIMEZONE);

        // Assert
        $this->assertSame(2, $report['pending_attendance']);
    }

    public function testItReportViewRendersWithoutData(): void
    {
        // Arrange
        $this->mockQueries();
        $report = $this->service->report($this->user->id, self::TIMEZONE);

        // Act
        $html = view('dashboard.reports', ['report' => $report])->render();

        // Assert
        $this->assertStringNotContainsString('Agenda de Hoje', $html);
        $this->assertStringNotContainsString('Próximos dias', $html);
        $this->assertStringContainsString('pending_attendance=1', $html);
    }
    public function testItReportAlertsNoShowStreakFromMostRecentSessions(): void
    {
        // Arrange
        $this->mockQueries([
            'closed' => new EloquentCollection([
                $this->makeSession(1, AgendaStatusEnum::NO_SHOW, '2026-12-20 12:00:00'),
                $this->makeSession(1, AgendaStatusEnum::NO_SHOW, '2026-12-10 12:00:00'),
                $this->makeSession(1, AgendaStatusEnum::COMPLETED, '2026-12-01 12:00:00'),
                $this->makeSession(2, AgendaStatusEnum::NO_SHOW, '2026-12-20 12:00:00'),
                $this->makeSession(2, AgendaStatusEnum::COMPLETED, '2026-12-10 12:00:00'),
                $this->makeSession(3, AgendaStatusEnum::COMPLETED, '2026-12-20 12:00:00'),
                $this->makeSession(3, AgendaStatusEnum::NO_SHOW, '2026-12-10 12:00:00'),
                $this->makeSession(3, AgendaStatusEnum::NO_SHOW, '2026-12-01 12:00:00'),
            ]),
            'patients' => collect([1 => $this->makePatient(1, 'Ana')]),
        ]);

        // Act
        $alert = $this->service->report($this->user->id, self::TIMEZONE)['alerts']['no_show_streak'];

        // Assert
        $this->assertSame(1, $alert['count']);
        $this->assertSame('Ana', $alert['items'][0]['name']);
        $this->assertSame('2 faltas seguidas', $alert['items'][0]['detail']);
    }

    public function testItReportAlertsScaleOverdueOnlyForPatientsInTreatment(): void
    {
        // Arrange: 1 em atendimento e escala antiga; 2 em atendimento e escala recente; 3 sem sessão recente
        $this->mockQueries([
            'closed' => new EloquentCollection([
                $this->makeSession(1, AgendaStatusEnum::COMPLETED, '2026-12-20 12:00:00'),
                $this->makeSession(2, AgendaStatusEnum::COMPLETED, '2026-12-20 12:00:00'),
                $this->makeSession(3, AgendaStatusEnum::COMPLETED, '2026-11-10 12:00:00'),
            ]),
            'last_scales' => collect([1 => '2026-11-14', 2 => '2026-12-15', 3 => '2026-09-01']),
            'patients' => collect([1 => $this->makePatient(1, 'Ana'), 3 => $this->makePatient(3, 'Caio')]),
        ]);

        // Act
        $alert = $this->service->report($this->user->id, self::TIMEZONE)['alerts']['scale_overdue'];

        // Assert
        $this->assertSame(1, $alert['count']);
        $this->assertSame('Ana', $alert['items'][0]['name']);
        $this->assertSame('Última escala há 45 dias', $alert['items'][0]['detail']);
    }

    public function testItReportAbandonmentIgnoresDeletedPatientsAndLimitsItems(): void
    {
        // Arrange
        $inactive = collect(range(1, 8))->map(fn (int $id) => (object) ['patient_id' => $id, 'last_session_on' => '2026-11-19 15:00:00']);
        $patients = collect(range(1, 7))->mapWithKeys(fn (int $id) => [$id => $this->makePatient($id, 'Paciente '.$id)]);
        $this->mockQueries(['inactive' => $inactive, 'patients' => $patients]);

        // Act
        $alert = $this->service->report($this->user->id, self::TIMEZONE)['alerts']['abandonment'];

        // Assert
        $this->assertSame(7, $alert['count']);
        $this->assertCount(5, $alert['items']);
        $this->assertSame('Última sessão há 40 dias', $alert['items'][0]['detail']);
    }

    public function testItReportListsTodaySessionsInUserTimezone(): void
    {
        // Arrange
        $session = $this->makeSession(1, AgendaStatusEnum::SCHEDULED, '2026-12-29 17:30:00')->forceFill(['room_link' => 'https://meet.test/abc']);
        $session->setRelation('patient', $this->makePatient(1, 'Ana'));
        $this->mockQueries(['today' => new EloquentCollection([$session])]);

        // Act
        $sessions = $this->service->report($this->user->id, self::TIMEZONE)['today_sessions'];

        // Assert
        $this->assertSame([['patient_id' => 1, 'name' => 'Ana', 'time' => '14:30', 'status' => 'scheduled', 'room_link' => 'https://meet.test/abc']], $sessions);
    }
    public function testItReportKpisCompareRevenueAndAttendanceWithPreviousPeriod(): void
    {
        // Arrange
        $this->mockQueries([
            'counts' => [['scheduled' => 0], ['completed' => 9, 'no_show' => 1], ['completed' => 4, 'no_show' => 1]],
            'paid' => [['total' => 150.0, 'count' => 2], ['total' => 100.0, 'count' => 1]],
        ]);

        // Act
        $kpis = $this->service->report($this->user->id, self::TIMEZONE)['kpis'];

        // Assert
        $this->assertSame(['total' => 150.0, 'variation' => 50.0], $kpis['revenue']);
        $this->assertSame(['rate' => 90.0, 'variation' => 10.0], $kpis['attendance']);
        $this->assertSame(['rate' => null, 'free_hours' => 0.0], $kpis['occupancy']);
    }

    public function testItReportKpisHaveNoVariationWithoutPreviousData(): void
    {
        // Arrange
        $this->mockQueries(['counts' => [['scheduled' => 0], ['completed' => 3], []]]);

        // Act
        $kpis = $this->service->report($this->user->id, self::TIMEZONE)['kpis'];

        // Assert
        $this->assertNull($kpis['revenue']['variation']);
        $this->assertSame(100.0, $kpis['attendance']['rate']);
        $this->assertNull($kpis['attendance']['variation']);
    }

    public function testItReportWeeklySessionsGroupsByWeekInUserTimezone(): void
    {
        // Arrange
        $this->mockQueries(['weekly' => new EloquentCollection([
            $this->makeSession(1, AgendaStatusEnum::COMPLETED, '2026-12-29 13:00:00'),
            $this->makeSession(1, AgendaStatusEnum::NO_SHOW, '2026-12-21 13:00:00'),
            $this->makeSession(2, AgendaStatusEnum::CANCELED, '2026-12-21 13:00:00'),
        ])]);

        // Act
        $chart = $this->service->report($this->user->id, self::TIMEZONE)['weekly_sessions'];

        // Assert
        $this->assertCount(8, $chart['categories']);
        $this->assertSame('28/12', $chart['categories'][7]);
        $this->assertSame([0, 0, 0, 0, 0, 0, 0, 1], $chart['completed']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 1, 0], $chart['no_show']);
        $this->assertSame([0, 0, 0, 0, 0, 0, 1, 0], $chart['canceled']);
    }
}
