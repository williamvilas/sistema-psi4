<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\PaymentOrderStatusEnum;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TIMEZONE = 'America/Sao_Paulo';

    private $paymentOrder;
    private $service;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentOrder = Mockery::mock(PaymentOrder::class);
        $this->service = new ReportService($this->paymentOrder);

        $this->user = User::factory()->create();
        Auth::login($this->user);
    }

    /**
     * @param  array<string, array{total: float, count: int}>  $byStatus  indicadores do período consultado
     * @param  array<string, array{total: float, count: int}>  $previous  indicadores do período anterior
     */
    private function mockFinancial(array $byStatus = [], array $methods = [], array $orders = [], array $previous = []): void
    {
        $this->paymentOrder->shouldReceive('listPaidByPeriod')
            ->once()
            ->with($this->user->id, Mockery::type(Carbon::class), Mockery::type(Carbon::class))
            ->andReturn($orders);

        $this->paymentOrder->shouldReceive('summarizePaidByPaymentMethodAndPeriod')
            ->once()
            ->with($this->user->id, Mockery::type(Carbon::class), Mockery::type(Carbon::class))
            ->andReturn($methods);

        $currentStart = null;
        $this->paymentOrder->shouldReceive('summarizeByStatusesAndPeriod')
            ->times(6)
            ->with($this->user->id, Mockery::type('array'), Mockery::type(Carbon::class), Mockery::type(Carbon::class))
            ->andReturnUsing(function (int $userId, array $statuses, Carbon $start) use (&$currentStart, $byStatus, $previous) {
                // O período consultado é sempre o primeiro a ser resumido; o anterior começa antes dele
                $currentStart ??= $start->copy();
                $source = $start->lt($currentStart) ? $previous : $byStatus;

                return $source[$this->statusKey($statuses)] ?? ['total' => 0.0, 'count' => 0];
            });
    }

    private function statusKey(array $statuses): string
    {
        return match (array_map(fn (PaymentOrderStatusEnum $status): string => $status->value, $statuses)) {
            ['pending', 'expired'] => 'pending',
            ['refunded'] => 'refunded',
            ['paid'] => 'paid',
        };
    }

    public function testItFinancialWithPayments(): void
    {
        // Arrange
        $this->mockFinancial([
            'pending' => ['total' => 200.0, 'count' => 2],
            'refunded' => ['total' => 20.0, 'count' => 1],
            'paid' => ['total' => 150.0, 'count' => 2],
        ]);

        // Act
        $report = $this->service->financial($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-05');

        // Assert
        $this->assertSame(['total' => 200.0, 'count' => 2, 'variation' => null], $report['pending']);
        $this->assertSame(['total' => 20.0, 'count' => 1, 'variation' => null], $report['refunded']);
        $this->assertSame(['total' => 150.0, 'count' => 2, 'variation' => null], $report['paid']);
        $this->assertSame(['total' => 75.0, 'count' => 2, 'variation' => null], $report['average_ticket']);
    }

    public function testItFinancialPaymentMethodsPercentages(): void
    {
        // Arrange
        $this->mockFinancial([], [
            ['method' => 'pix', 'count' => 3, 'total' => 300.0],
            ['method' => 'card', 'count' => 1, 'total' => 100.0],
        ]);

        // Act
        $report = $this->service->financial($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-05');

        // Assert
        $this->assertSame(['label' => 'PIX', 'count' => 3, 'total' => 300.0, 'percent' => 75.0], $report['payment_methods'][0]);
        $this->assertSame(['label' => 'Cartão', 'count' => 1, 'total' => 100.0, 'percent' => 25.0], $report['payment_methods'][1]);
    }

    public function testItFinancialPaymentMethodsRoundsPercent(): void
    {
        // Arrange
        $this->mockFinancial([], [
            ['method' => 'pix', 'count' => 2, 'total' => 200.0],
            ['method' => 'card', 'count' => 1, 'total' => 100.0],
        ]);

        // Act
        $report = $this->service->financial($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-05');

        // Assert
        $this->assertSame(66.7, $report['payment_methods'][0]['percent']);
        $this->assertSame(33.3, $report['payment_methods'][1]['percent']);
    }

    public function testItFinancialPaymentMethodsEmpty(): void
    {
        // Arrange
        $this->mockFinancial();

        // Act
        $report = $this->service->financial($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-05');

        // Assert
        $this->assertSame([], $report['payment_methods']);
    }

    private function order(string $utcDate, float $amount): array
    {
        return ['created_at' => Carbon::parse($utcDate, 'UTC'), 'amount' => $amount];
    }

    public function testItFinancialReceivedGroupsByDay(): void
    {
        // Arrange
        $this->mockFinancial([], [], [
            $this->order('2026-10-01 12:00:00', 100.0),
            $this->order('2026-10-01 15:00:00', 50.5),
            $this->order('2026-10-03 12:00:00', 30.0),
        ]);

        // Act
        $received = $this->service->financial($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-05')['received'];

        // Assert
        $this->assertSame('day', $received['granularity']);
        $this->assertCount(5, $received['data']);
        $this->assertSame(['label' => '01/10', 'total' => 150.5, 'count' => 2], $received['data'][0]);
        $this->assertSame(['label' => '02/10', 'total' => 0.0, 'count' => 0], $received['data'][1]);
        $this->assertSame(30.0, $received['data'][2]['total']);
    }

    public function testItFinancialReceivedUsesUserTimezoneToBucket(): void
    {
        // Arrange: 02:00 UTC do dia 02 ainda é dia 01 em São Paulo
        $this->mockFinancial([], [], [$this->order('2026-10-02 02:00:00', 10.0)]);

        // Act
        $received = $this->service->financial($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-05')['received'];

        // Assert
        $this->assertSame(10.0, $received['data'][0]['total']);
        $this->assertSame(0.0, $received['data'][1]['total']);
    }

    public function testItFinancialReceivedGroupsByWeek(): void
    {
        // Arrange: 2026-10-01 é quinta; semanas iniciam na segunda
        $this->mockFinancial([], [], [
            $this->order('2026-10-02 12:00:00', 10.0),
            $this->order('2026-10-06 12:00:00', 20.0),
        ]);

        // Act
        $received = $this->service->financial($this->user->id, self::TIMEZONE, '2026-10-01', '2026-11-15')['received'];

        // Assert
        $this->assertSame('week', $received['granularity']);
        $this->assertSame('01/10 - 04/10', $received['data'][0]['label']);
        $this->assertSame(10.0, $received['data'][0]['total']);
        $this->assertSame('05/10 - 11/10', $received['data'][1]['label']);
        $this->assertSame(20.0, $received['data'][1]['total']);
        $this->assertSame('09/11 - 15/11', $received['data'][6]['label']);
    }

    public function testItFinancialReceivedGroupsByMonth(): void
    {
        // Arrange
        $this->mockFinancial([], [], [
            $this->order('2026-02-10 12:00:00', 10.0),
            $this->order('2026-02-20 12:00:00', 5.0),
        ]);

        // Act
        $received = $this->service->financial($this->user->id, self::TIMEZONE, '2026-01-15', '2026-06-15')['received'];

        // Assert
        $this->assertSame('month', $received['granularity']);
        $this->assertCount(6, $received['data']);
        $this->assertSame(['label' => '02/2026', 'total' => 15.0, 'count' => 2], $received['data'][1]);
    }

    public function testItFinancialReceivedGroupsByYear(): void
    {
        // Arrange
        $this->mockFinancial([], [], [$this->order('2025-06-10 12:00:00', 40.0)]);

        // Act
        $received = $this->service->financial($this->user->id, self::TIMEZONE, '2024-10-01', '2026-10-05')['received'];

        // Assert
        $this->assertSame('year', $received['granularity']);
        $this->assertSame(['2024', '2025', '2026'], array_column($received['data'], 'label'));
        $this->assertSame(40.0, $received['data'][1]['total']);
    }

    public function testItFinancialReceivedBoundaryBetweenGranularities(): void
    {
        // Arrange / Act
        $granularities = [];
        foreach ([['2026-10-01', '2026-10-31'], ['2026-10-01', '2026-11-01'], ['2026-10-01', '2026-12-31'], ['2026-10-01', '2027-01-01']] as [$start, $end]) {
            $this->mockFinancial();
            $granularities[] = $this->service->financial($this->user->id, self::TIMEZONE, $start, $end)['received']['granularity'];
        }

        // Assert
        $this->assertSame(['day', 'week', 'week', 'month'], $granularities);
    }

    public function testItFinancialReceivedWithoutPayments(): void
    {
        // Arrange
        $this->mockFinancial();

        // Act
        $received = $this->service->financial($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-02')['received'];

        // Assert
        $this->assertSame([0.0, 0.0], array_column($received['data'], 'total'));
    }

    public function testItFinancialWithoutPayments(): void
    {
        // Arrange
        $this->mockFinancial();

        // Act
        $report = $this->service->financial($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-03');

        // Assert
        $this->assertSame(0.0, $report['pending']['total']);
        $this->assertSame(0.0, $report['refunded']['total']);
        $this->assertSame(0.0, $report['paid']['total']);
        $this->assertSame(0.0, $report['average_ticket']['total']);
    }

    public function testItFinancialRoundsAverageTicket(): void
    {
        // Arrange
        $this->mockFinancial(['paid' => ['total' => 100.0, 'count' => 3]]);

        // Act
        $report = $this->service->financial($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-05');

        // Assert
        $this->assertSame(33.33, $report['average_ticket']['total']);
    }

    public function testItFinancialQueriesPeriodsInUtcFromUserTimezone(): void
    {
        // Arrange
        $periods = [];
        $this->paymentOrder->shouldReceive('summarizePaidByPaymentMethodAndPeriod')->andReturn([]);
        $this->paymentOrder->shouldReceive('listPaidByPeriod')->andReturn([]);
        $this->paymentOrder->shouldReceive('summarizeByStatusesAndPeriod')
            ->times(6)
            ->andReturnUsing(function (int $userId, array $statuses, Carbon $start, Carbon $end) use (&$periods) {
                $periods[] = [$userId, $start->toDateTimeString(), $end->toDateTimeString()];

                return ['total' => 0.0, 'count' => 0];
            });

        // Act
        $this->service->financial($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-05');

        // Assert: período consultado e os 5 dias imediatamente anteriores, ambos convertidos para UTC
        $current = [$this->user->id, '2026-10-01 03:00:00', '2026-10-06 02:59:59'];
        $previous = [$this->user->id, '2026-09-26 03:00:00', '2026-10-01 02:59:59'];
        $this->assertSame([$current, $current, $current, $previous, $previous, $previous], $periods);
    }

    public function testItFinancialSumsPendingAndExpiredAsPending(): void
    {
        // Arrange
        $statuses = [];
        $this->paymentOrder->shouldReceive('summarizePaidByPaymentMethodAndPeriod')->andReturn([]);
        $this->paymentOrder->shouldReceive('listPaidByPeriod')->andReturn([]);
        $this->paymentOrder->shouldReceive('summarizeByStatusesAndPeriod')
            ->andReturnUsing(function (int $userId, array $given) use (&$statuses) {
                $statuses[] = $given;

                return ['total' => 0.0, 'count' => 0];
            });

        // Act
        $this->service->financial($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-05');

        // Assert
        $this->assertContains([PaymentOrderStatusEnum::PENDING, PaymentOrderStatusEnum::EXPIRED], $statuses);
        $this->assertNotContains([PaymentOrderStatusEnum::PENDING], $statuses);
    }

    public function testItFinancialComparesWithPreviousPeriod(): void
    {
        // Arrange
        $this->mockFinancial(
            byStatus: [
                'paid' => ['total' => 300.0, 'count' => 3],
                'pending' => ['total' => 50.0, 'count' => 1],
                'refunded' => ['total' => 10.0, 'count' => 1],
            ],
            previous: [
                'paid' => ['total' => 200.0, 'count' => 1],
                'pending' => ['total' => 100.0, 'count' => 2],
                'refunded' => ['total' => 10.0, 'count' => 1],
            ],
        );

        // Act
        $report = $this->service->financial($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-16');

        // Assert
        $this->assertSame(50.0, $report['paid']['variation']);
        $this->assertSame(-50.0, $report['pending']['variation']);
        $this->assertSame(0.0, $report['refunded']['variation']);
        $this->assertSame(-50.0, $report['average_ticket']['variation']);
        $this->assertSame(['start' => '15/09/2026', 'end' => '30/09/2026'], $report['previous_period']);
    }

    public function testItFinancialVariationIsNullWithoutPreviousValue(): void
    {
        // Arrange
        $this->mockFinancial(['paid' => ['total' => 100.0, 'count' => 1]]);

        // Act
        $report = $this->service->financial($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-05');

        // Assert
        $this->assertNull($report['paid']['variation']);
        $this->assertNull($report['average_ticket']['variation']);
    }

    public function testItFinancialPreviousPeriodOfSingleDay(): void
    {
        // Arrange
        $this->mockFinancial();

        // Act
        $report = $this->service->financial($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-01');

        // Assert
        $this->assertSame(['start' => '30/09/2026', 'end' => '30/09/2026'], $report['previous_period']);
    }

    public function testItFinancialPaymentMethodsGroupsNotInformed(): void
    {
        // Arrange
        $this->mockFinancial([], [
            ['method' => 'pix', 'count' => 1, 'total' => 100.0],
            ['method' => null, 'count' => 1, 'total' => 1.21],
        ]);

        // Act
        $report = $this->service->financial($this->user->id, self::TIMEZONE, '2026-10-01', '2026-10-05');

        // Assert
        $this->assertSame(['label' => 'Não informado', 'count' => 1, 'total' => 1.21, 'percent' => 50.0], $report['payment_methods'][1]);
    }
}
