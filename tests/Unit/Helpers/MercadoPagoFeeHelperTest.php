<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\MercadoPagoFeeHelper;
use Tests\TestCase;

class MercadoPagoFeeHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('mercadopago.fees', [
            'checked_at' => '2026-10-31',
            'pix' => 1.5,
            'card' => 5,
            'card_release_days' => 14,
            'installments' => [['from' => 2, 'to' => 6, 'percent' => 3.09]],
            'installments_patient_max' => 6.25,
        ]);
    }

    public function testItBuildsRowsFromConfig(): void
    {
        // Act
        $rows = MercadoPagoFeeHelper::rows();

        // Assert
        $this->assertSame(['1,5%', '5%', '3,09%'], array_column($rows, 'percent'));
        $this->assertSame(__('payments.fees.term_now'), $rows[0]['term']);
        $this->assertSame('recebe em 14 dias', $rows[1]['term']);
        $this->assertSame('Cartão de crédito de 2x a 6x', $rows[2]['label']);
    }

    public function testItFormatsPatientMaxAndCheckedDate(): void
    {
        // Act & Assert
        $this->assertSame('6,25%', MercadoPagoFeeHelper::installmentsPatientMax());
        $this->assertSame('31/10/2026', MercadoPagoFeeHelper::checkedAt());
    }

    public function testItSumsPlanFeeWithMercadoPagoFeeByPaymentMethod(): void
    {
        // Act
        $withPlanFee = MercadoPagoFeeHelper::planFeeLabels(2.5);
        $withoutPlanFee = MercadoPagoFeeHelper::planFeeLabels(0);

        // Assert
        $this->assertSame([
            __('plans.payment_fee_pix', ['percent' => '4%']),
            __('plans.payment_fee_card', ['percent' => '7,5%']),
        ], $withPlanFee);
        $this->assertSame([
            __('plans.payment_fee_pix', ['percent' => '1,5%']),
            __('plans.payment_fee_card', ['percent' => '5%']),
        ], $withoutPlanFee);
    }

    public function testItFormatsPercentWithoutTrailingZeros(): void
    {
        // Act & Assert
        $this->assertSame('0,99%', MercadoPagoFeeHelper::formatPercent(0.99));
        $this->assertSame('2%', MercadoPagoFeeHelper::formatPercent(2));
        $this->assertSame('10%', MercadoPagoFeeHelper::formatPercent(10));
        $this->assertSame('0%', MercadoPagoFeeHelper::formatPercent(0));
    }
}
