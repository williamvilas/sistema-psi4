<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PaymentOrderIntegrationStatusEnum;
use App\Enums\PaymentOrderStatusEnum;
use App\Models\PaymentAccount;
use App\Models\PaymentOrder;
use App\Services\PaymentAccountService;
use App\Services\PaymentOrderService;
use App\Wrappers\MercadoPagoConnectWrapper;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Cria a cobrança (preference do Checkout Pro) diretamente na conta Mercado
 * Pago conectada do terapeuta (Mercado Pago Connect), substituindo o antigo
 * `SendPaymentOrderToApiJob` (payments.local).
 */
class CreateMercadoPagoChargeJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct(public int $paymentOrderId)
    {
    }

    public function uniqueId(): string
    {
        return 'payment_order_'.$this->paymentOrderId;
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('payment_order_'.$this->paymentOrderId))
                ->releaseAfter(15)
                ->expireAfter(120),
        ];
    }

    public function backoff(): array
    {
        return [10, 30, 90, 300];
    }

    public function handle(
        MercadoPagoConnectWrapper $wrapper,
        PaymentAccountService $paymentAccountService,
        PaymentOrderService $paymentOrderService,
    ): void {
        $order = new PaymentOrder()->findByIdWithPatientEmail(
            $this->paymentOrderId,
            ['id', 'uuid', 'user_id', 'patient_id', 'amount', 'description', 'payment_methods', 'due_date', 'status', 'integration_status'],
        );

        // Pedido já integrado: reenviar duplicaria a cobrança no Mercado Pago.
        // Pedido que deixou de estar pendente (pago/cancelado) não deve gerar checkout.
        if (
            ! $order
            || $order->integration_status === PaymentOrderIntegrationStatusEnum::INTEGRATED
            || $order->status !== PaymentOrderStatusEnum::PENDING
        ) {
            return;
        }

        if ($order->integration_status !== PaymentOrderIntegrationStatusEnum::PROCESSING) {
            $order->update(['integration_status' => PaymentOrderIntegrationStatusEnum::PROCESSING->value]);
        }

        try {
            $account = $this->getIntegratedAccount($order->user_id);
            $accessToken = $paymentAccountService->getValidAccessToken($account);
            $result = $wrapper->createPreference($accessToken, $paymentOrderService->buildPreferenceBody($order));
        } catch (Throwable $t) {
            // Erro de negócio não se resolve com novas tentativas
            if ($t instanceof \DomainException) {
                $this->fail($t);

                return;
            }

            throw $t;
        }

        $order->update([
            'integration_status' => PaymentOrderIntegrationStatusEnum::INTEGRATED->value,
            'external_id' => $result['id'],
            'checkout_url' => $result['init_point'],
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $order = new PaymentOrder()->findById($this->paymentOrderId, ['id', 'integration_status']);

        // Nunca rebaixa um pedido já integrado (ex.: falha tardia após criar o checkout)
        if ($order && $order->integration_status !== PaymentOrderIntegrationStatusEnum::INTEGRATED) {
            $order->update(['integration_status' => PaymentOrderIntegrationStatusEnum::ERROR->value]);
        }
    }

    /**
     * A conta pode ainda não estar conectada. Sem conta integrada o job falha
     * definitivamente: reconectar exige uma nova ação do terapeuta (OAuth),
     * então novas tentativas automáticas não resolveriam o problema.
     */
    private function getIntegratedAccount(int $userId): PaymentAccount
    {
        $account = new PaymentAccount()->findIntegratedByUser($userId, ['id', 'access_token', 'refresh_token', 'token_expires_at']);

        if (! $account) {
            throw new \DomainException(__('payments.account_not_connected'));
        }

        return $account;
    }
}
