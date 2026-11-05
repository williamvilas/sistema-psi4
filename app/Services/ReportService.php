<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentOrderStatusEnum;
use App\Models\PaymentOrder;
use App\Traits\ReportPeriodTrait;
use Carbon\Carbon;

readonly class ReportService
{
    use ReportPeriodTrait;

    private const DAY_LIMIT = 31;
    private const WEEK_LIMIT = 92;
    private const MONTH_LIMIT = 366;

    public function __construct(private PaymentOrder $paymentOrder)
    {
    }

    /**
     * Relatório financeiro por status do pagamento, com as datas informadas no fuso do usuário.
     * Os indicadores trazem a variação percentual frente ao período anterior de mesma duração.
     */
    public function financial(int $userId, string $timezone, string $startDate, string $endDate): array
    {
        [$start, $end] = $this->period($timezone, $startDate, $endDate);
        [$previousStart, $previousEnd] = $this->previousPeriod($start, $end);

        $current = $this->indicators($userId, $start, $end);
        $previous = $this->indicators($userId, $previousStart, $previousEnd);

        $withVariation = fn (string $key): array => $current[$key] + [
            'variation' => $this->variation($current[$key]['total'], $previous[$key]['total']),
        ];

        return [
            'pending' => $withVariation('pending'),
            'refunded' => $withVariation('refunded'),
            'paid' => $withVariation('paid'),
            'average_ticket' => $withVariation('average_ticket'),
            'previous_period' => ['start' => $previousStart->format('d/m/Y'), 'end' => $previousEnd->format('d/m/Y')],
            'payment_methods' => $this->paymentMethods($userId, $start, $end),
            'received' => $this->receivedByPeriod($userId, $start, $end),
        ];
    }

    /**
     * Pendentes somam as ordens ainda em aberto e as expiradas sem pagamento.
     *
     * @return array<string, array{total: float, count: int}>
     */
    private function indicators(int $userId, Carbon $start, Carbon $end): array
    {
        $summary = fn (array $statuses): array => $this->summarize($userId, $statuses, $start, $end);

        $paid = $summary([PaymentOrderStatusEnum::PAID]);

        return [
            'pending' => $summary([PaymentOrderStatusEnum::PENDING, PaymentOrderStatusEnum::EXPIRED]),
            'refunded' => $summary([PaymentOrderStatusEnum::REFUNDED]),
            'paid' => $paid,
            'average_ticket' => [
                'total' => $paid['count'] > 0 ? round($paid['total'] / $paid['count'], 2) : 0.0,
                'count' => $paid['count'],
            ],
        ];
    }

    /**
     * Participação (por quantidade) de cada meio de pagamento nas ordens pagas do período.
     *
     * @return array<int, array{label: string, count: int, total: float, percent: float}>
     */
    private function paymentMethods(int $userId, Carbon $start, Carbon $end): array
    {
        $rows = $this->paymentOrder->summarizePaidByPaymentMethodAndPeriod($userId, $start->copy()->utc(), $end->copy()->utc());
        $count = array_sum(array_column($rows, 'count'));

        return array_map(fn (array $row): array => [
            'label' => $row['method'] === null ? __('reports.payment_method_not_informed') : PaymentMethodEnum::from($row['method'])->getText(),
            'count' => $row['count'],
            'total' => round($row['total'], 2),
            'percent' => round($row['count'] / $count * 100, 1),
        ], $rows);
    }

    /**
     * Total recebido (ordens pagas) agrupado por dia, semana, mês ou ano conforme o tamanho do período.
     * Intervalos sem pagamento ficam com total zero.
     *
     * @return array{granularity: string, data: array<int, array{label: string, total: float, count: int}>}
     */
    private function receivedByPeriod(int $userId, Carbon $start, Carbon $end): array
    {
        $granularity = $this->granularity($start, $end);
        $buckets = $this->buckets($granularity, $start, $end);
        $orders = $this->paymentOrder->listPaidByPeriod($userId, $start->copy()->utc(), $end->copy()->utc());

        foreach ($orders as $order) {
            $key = $this->bucketStart($granularity, $order['created_at']->copy()->setTimezone($start->getTimezone()))->format('Y-m-d');

            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['total'] += $order['amount'];
            $buckets[$key]['count']++;
        }

        return [
            'granularity' => $granularity,
            'data' => array_values(array_map(fn (array $bucket): array => [
                'label' => $bucket['label'],
                'total' => round($bucket['total'], 2),
                'count' => $bucket['count'],
            ], $buckets)),
        ];
    }

    private function granularity(Carbon $start, Carbon $end): string
    {
        $days = $this->days($start, $end);

        return match (true) {
            $days <= self::DAY_LIMIT => 'day',
            $days <= self::WEEK_LIMIT => 'week',
            $days <= self::MONTH_LIMIT => 'month',
            default => 'year',
        };
    }

    private function bucketStart(string $granularity, Carbon $date): Carbon
    {
        $date = $date->copy();

        return match ($granularity) {
            'day' => $date->startOfDay(),
            'week' => $date->startOfWeek(Carbon::MONDAY),
            'month' => $date->startOfMonth(),
            default => $date->startOfYear(),
        };
    }

    /**
     * @return array<string, array{label: string, total: float, count: int}> indexado pelo início do intervalo (Y-m-d)
     */
    private function buckets(string $granularity, Carbon $start, Carbon $end): array
    {
        $buckets = [];
        $cursor = $this->bucketStart($granularity, $start);

        while ($cursor->lte($end)) {
            $buckets[$cursor->format('Y-m-d')] = [
                'label' => $this->bucketLabel($granularity, $cursor, $start, $end),
                'total' => 0.0,
                'count' => 0,
            ];

            $cursor = match ($granularity) {
                'day' => $cursor->copy()->addDay(),
                'week' => $cursor->copy()->addWeek(),
                'month' => $cursor->copy()->addMonthNoOverflow(),
                default => $cursor->copy()->addYear(),
            };
        }

        return $buckets;
    }

    private function bucketLabel(string $granularity, Carbon $bucketStart, Carbon $start, Carbon $end): string
    {
        return match ($granularity) {
            'day' => $bucketStart->format('d/m'),
            'week' => max($bucketStart, $start)->format('d/m').' - '.min($bucketStart->copy()->endOfWeek(Carbon::SUNDAY), $end)->format('d/m'),
            'month' => $bucketStart->format('m/Y'),
            default => $bucketStart->format('Y'),
        };
    }

    /**
     * @param  array<int, PaymentOrderStatusEnum>  $statuses
     */
    private function summarize(int $userId, array $statuses, Carbon $start, Carbon $end): array
    {
        $row = $this->paymentOrder->summarizeByStatusesAndPeriod($userId, $statuses, $start->copy()->utc(), $end->copy()->utc());

        return ['total' => round($row['total'], 2), 'count' => $row['count']];
    }
}
