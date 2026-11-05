<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AgendaStatusEnum;
use App\Models\Agenda;
use App\Models\Patients;
use App\Models\User;
use App\Services\PatientEvolutionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class PatientEvolutionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TIMEZONE = 'America/Sao_Paulo';

    private $agenda;
    private $patients;
    private $service;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 18/10/2026 12:00 em São Paulo
        Carbon::setTestNow(Carbon::parse('2026-10-18 15:00:00', 'UTC'));

        $this->user = User::factory()->create();
        Auth::login($this->user);

        $this->agenda = Mockery::mock(Agenda::class);
        $this->patients = Mockery::mock(Patients::class);
        $this->service = new PatientEvolutionService($this->agenda, $this->patients);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function mockPatientExists(bool $exists = true): void
    {
        $this->patients->shouldReceive('findById')
            ->with(10, $this->user->id, ['id'])
            ->andReturn($exists ? (new Patients)->forceFill(['id' => 10]) : null);
    }

    private function makeSession(AgendaStatusEnum $status, string $scheduledOnUtc): Agenda
    {
        return (new Agenda)->forceFill(['status' => $status, 'scheduled_on' => Carbon::parse($scheduledOnUtc, 'UTC')]);
    }

    public function testItAttendanceComputesIndicatorsForPeriod(): void
    {
        // Arrange
        $this->mockPatientExists();
        $this->agenda->shouldReceive('listClosedByPatient')
            ->once()
            ->with($this->user->id, 10, Mockery::on(fn (Carbon $since) => $since->eq(Carbon::parse('2026-04-18 03:00:00', 'UTC'))), Mockery::type(Carbon::class))
            ->andReturn(new EloquentCollection([
                $this->makeSession(AgendaStatusEnum::COMPLETED, '2026-09-01 13:00:00'),
                $this->makeSession(AgendaStatusEnum::NO_SHOW, '2026-09-08 13:00:00'),
                $this->makeSession(AgendaStatusEnum::COMPLETED, '2026-09-15 13:00:00'),
                $this->makeSession(AgendaStatusEnum::CANCELED, '2026-09-22 13:00:00'),
                $this->makeSession(AgendaStatusEnum::COMPLETED, '2026-10-06 13:00:00'),
            ]));
        $this->agenda->shouldReceive('firstCompletedByPatient')
            ->with($this->user->id, 10)
            ->andReturn($this->makeSession(AgendaStatusEnum::COMPLETED, '2025-10-01 13:00:00'));

        // Act
        $result = $this->service->attendance(10, $this->user->id, self::TIMEZONE, 6);

        // Assert
        $this->assertSame(6, $result['months']);
        $this->assertSame(3, $result['completed']);
        $this->assertSame(1, $result['no_show']);
        $this->assertSame(1, $result['canceled']);
        $this->assertSame(75.0, $result['presence_rate']);
        $this->assertSame(17.5, $result['avg_interval_days']);
        $this->assertSame('2025-10-01 10:00', $result['first_session_on']->format('Y-m-d H:i'));
        $this->assertSame('2026-10-06 10:00', $result['last_session_on']->format('Y-m-d H:i'));
    }

    public function testItAttendanceWithWholeTreatmentQueriesWithoutStartDate(): void
    {
        // Arrange
        $this->mockPatientExists();
        $this->agenda->shouldReceive('listClosedByPatient')
            ->once()
            ->with($this->user->id, 10, null, Mockery::type(Carbon::class))
            ->andReturn(new EloquentCollection([$this->makeSession(AgendaStatusEnum::COMPLETED, '2024-01-10 13:00:00')]));
        $this->agenda->shouldReceive('firstCompletedByPatient')->andReturn($this->makeSession(AgendaStatusEnum::COMPLETED, '2024-01-10 13:00:00'));

        // Act
        $result = $this->service->attendance(10, $this->user->id, self::TIMEZONE, 0);

        // Assert
        $this->assertSame(1, $result['completed']);
        $this->assertSame(100.0, $result['presence_rate']);
        $this->assertNull($result['avg_interval_days']);
    }

    public function testItAttendanceReturnsEmptyIndicatorsWithoutSessions(): void
    {
        // Arrange
        $this->mockPatientExists();
        $this->agenda->shouldReceive('listClosedByPatient')->andReturn(new EloquentCollection());
        $this->agenda->shouldReceive('firstCompletedByPatient')->andReturnNull();

        // Act
        $result = $this->service->attendance(10, $this->user->id, self::TIMEZONE, 3);

        // Assert
        $this->assertSame(0, $result['completed']);
        $this->assertSame(0, $result['no_show']);
        $this->assertSame(0, $result['canceled']);
        $this->assertNull($result['presence_rate']);
        $this->assertNull($result['avg_interval_days']);
        $this->assertNull($result['first_session_on']);
        $this->assertNull($result['last_session_on']);
    }

    public function testItAttendanceOnlyNoShowsHasZeroPresence(): void
    {
        // Arrange
        $this->mockPatientExists();
        $this->agenda->shouldReceive('listClosedByPatient')->andReturn(new EloquentCollection([
            $this->makeSession(AgendaStatusEnum::NO_SHOW, '2026-10-01 13:00:00'),
        ]));
        $this->agenda->shouldReceive('firstCompletedByPatient')->andReturnNull();

        // Act
        $result = $this->service->attendance(10, $this->user->id, self::TIMEZONE, 3);

        // Assert
        $this->assertSame(0.0, $result['presence_rate']);
    }

    public function testItAttendanceThrowExceptionWhenPatientBelongsToAnotherUser(): void
    {
        // Arrange
        $this->mockPatientExists(false);
        $this->agenda->shouldNotReceive('listClosedByPatient');

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('scales.patient_not_found'));

        // Act
        $this->service->attendance(10, $this->user->id, self::TIMEZONE, 6);
    }
}
