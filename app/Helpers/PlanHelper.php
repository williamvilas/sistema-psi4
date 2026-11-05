<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Cálculos de preço do plano anual a partir de `config('plans.plans')`.
 * Centraliza as contas para a tela e o checkout nunca divergirem.
 */
class PlanHelper
{
    public static function annualConfig(string $plan): ?array
    {
        $config = config('plans.plans.'.$plan.'.annual');

        return is_array($config) ? $config : null;
    }

    /** Total do ano no cartão: 12x o valor mensal do plano anual. */
    public static function annualTotal(string $plan): float
    {
        $annual = self::annualConfig($plan);

        if (! $annual) {
            return 0.0;
        }

        return round((float) $annual['monthly_price'] * 12, 2);
    }

    /** Percentual economizado no anual em relação a 12x o plano mensal (para exibição). */
    public static function annualDiscount(string $plan): int
    {
        $monthly = (float) config('plans.plans.'.$plan.'.price');

        if (! self::annualConfig($plan) || $monthly <= 0) {
            return 0;
        }

        return (int) round((1 - self::annualTotal($plan) / ($monthly * 12)) * 100);
    }

    /** Total do ano à vista no PIX: desconto adicional sobre o total anual. */
    public static function annualPixTotal(string $plan): float
    {
        $annual = self::annualConfig($plan);

        if (! $annual) {
            return 0.0;
        }

        return round(self::annualTotal($plan) * (1 - ((float) ($annual['pix_discount'] ?? 0) / 100)), 2);
    }

    public static function annualInstallments(string $plan): int
    {
        return max(1, (int) (self::annualConfig($plan)['installments'] ?? 1));
    }

    public static function annualInstallmentValue(string $plan): float
    {
        return round(self::annualTotal($plan) / self::annualInstallments($plan), 2);
    }

    /** Equivalente mensal do plano anual (para exibição na tela de planos). */
    public static function annualMonthlyEquivalent(string $plan): float
    {
        return round(self::annualTotal($plan) / 12, 2);
    }

    public static function annualDays(string $plan): int
    {
        return (int) (self::annualConfig($plan)['days'] ?? 365);
    }

    /** Taxa (%) do PSI4 sobre as cobranças pagas pelo Mercado Pago no plano. */
    public static function paymentFeePercent(string $plan): float
    {
        return max(0.0, (float) config('plans.plans.'.$plan.'.payment_fee_percent', 0));
    }

    /** Valor em reais da taxa do PSI4 (`marketplace_fee` do Mercado Pago) sobre uma cobrança. */
    public static function paymentFee(string $plan, float $amount): float
    {
        return round($amount * self::paymentFeePercent($plan) / 100, 2);
    }
}
