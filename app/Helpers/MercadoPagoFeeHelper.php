<?php

declare(strict_types=1);

namespace App\Helpers;

use Carbon\Carbon;

/**
 * Taxas do Mercado Pago para exibição, a partir de `config('mercadopago.fees')`.
 * Centraliza a leitura para as telas nunca divergirem.
 */
class MercadoPagoFeeHelper
{
    /** @return array<int, array{label: string, term: string, percent: string}> */
    public static function rows(): array
    {
        $fees = config('mercadopago.fees');
        $cardTerm = __('payments.fees.term_days', ['days' => $fees['card_release_days']]);

        $rows = [
            ['label' => __('payments.fees.pix'), 'term' => __('payments.fees.term_now'), 'percent' => self::formatPercent((float) $fees['pix'])],
            ['label' => __('payments.fees.card'), 'term' => $cardTerm, 'percent' => self::formatPercent((float) $fees['card'])],
        ];

        foreach ($fees['installments'] as $installment) {
            $rows[] = [
                'label' => __('payments.fees.installments', ['from' => $installment['from'], 'to' => $installment['to']]),
                'term' => $cardTerm,
                'percent' => self::formatPercent((float) $installment['percent']),
            ];
        }

        return $rows;
    }

    /**
     * Textos da taxa total de uma cobrança paga, por forma de pagamento: taxa do PSI4 no plano + taxa do Mercado Pago.
     *
     * @return array<int, string>
     */
    public static function planFeeLabels(float $psi4Percent): array
    {
        return array_map(
            fn (string $method) => __('plans.payment_fee_'.$method, [
                'percent' => self::formatPercent($psi4Percent + (float) config('mercadopago.fees.'.$method)),
            ]),
            ['pix', 'card']
        );
    }

    /** Taxa máxima quando o paciente assume os juros do parcelamento. */
    public static function installmentsPatientMax(): string
    {
        return self::formatPercent((float) config('mercadopago.fees.installments_patient_max'));
    }

    /** Data em que os valores foram conferidos no Mercado Pago. */
    public static function checkedAt(): string
    {
        return Carbon::parse(config('mercadopago.fees.checked_at'))->format('d/m/Y');
    }

    /** Percentual no formato brasileiro, sem zeros à direita (0,99% e 2%). */
    public static function formatPercent(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, ',', ''), '0'), ',').'%';
    }
}
