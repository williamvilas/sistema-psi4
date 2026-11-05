<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentOrderStatusEnum;
use App\Models\PaymentOrder;
use App\Models\Webhook;
use App\Wrappers\MercadoPagoWrapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processa a notificação webhook (`type=payment`) de uma cobrança criada via
 * Mercado Pago Connect na conta de um terapeuta conectado, atualizando o
 * status da ordem de pagamento do paciente.
 */
readonly class ProcessMercadoPagoChargeWebhookService
{
    public function __construct(
        private PaymentOrder $paymentOrder,
        private MercadoPagoWrapper $mercadoPagoWrapper,
        private HealthReceiptService $healthReceiptService,
    ) {
    }

    public function handle(Webhook $webhook): void
    {
        $paymentId = $webhook->getDataId();

        if (! $paymentId) {
            throw new \DomainException(__('payments.webhook_missing_data_id'));
        }

        $payment = $this->mercadoPagoWrapper->getPayment($paymentId);
        $externalReference = (string) ($payment['external_reference'] ?? '');

        if ($externalReference === '') {
            Log::warning("Pagamento {$paymentId} sem external_reference associado");

            return;
        }

        $order = $this->paymentOrder->findByUuid($externalReference, ['id', 'user_id', 'status', 'payment_method', 'paid_at']);

        if (! $order) {
            Log::warning("Ordem de pagamento não encontrada para external_reference {$externalReference}");

            return;
        }

        $status = $this->mapStatus((string) ($payment['status'] ?? ''));

        if (! $status) {
            Log::info('Status de pagamento do Mercado Pago não mapeado: '.($payment['status'] ?? 'null'));

            return;
        }

        // Notificação repetida (o Mercado Pago reenvia) não gera nova escrita
        if ($order->status === $status && ($status !== PaymentOrderStatusEnum::PAID || $order->paid_at)) {
            Log::info("Ordem de pagamento {$order->id} já está com status {$status->value}");

            return;
        }

        $data = [
            'status' => $status->value,
            'external_id' => (string) ($payment['id'] ?? $paymentId),
        ];

        if ($status === PaymentOrderStatusEnum::PAID) {
            $data['payment_method'] = $this->mapPaymentMethod((string) ($payment['payment_type_id'] ?? ''))?->value;
            $data['paid_at'] = $order->paid_at ?? $payment['date_approved'] ?? now();
        }

        // Na mesma transação: se o recibo falhar, a notificação é reprocessada e não fica pagamento sem recibo
        DB::transaction(function () use ($order, $data, $status) {
            $order->update($data);

            if ($status === PaymentOrderStatusEnum::PAID) {
                $this->healthReceiptService->createFromPaymentOrder($order->id);
            }

            if ($status === PaymentOrderStatusEnum::REFUNDED) {
                $this->healthReceiptService->handleRefund($order->id);
            }
        });

        Log::info("Ordem de pagamento {$order->id} atualizada para status {$status->value}");
    }

    private function mapStatus(string $status): ?PaymentOrderStatusEnum
    {
        return match ($status) {
            'approved' => PaymentOrderStatusEnum::PAID,
            'pending', 'in_process' => PaymentOrderStatusEnum::PROCESSING,
            'rejected' => PaymentOrderStatusEnum::REJECTED,
            'cancelled' => PaymentOrderStatusEnum::CANCELLED,
            'refunded' => PaymentOrderStatusEnum::REFUNDED,
            'charged_back', 'in_mediation' => PaymentOrderStatusEnum::REVERSING,
            default => null,
        };
    }

    private function mapPaymentMethod(string $paymentTypeId): ?PaymentMethodEnum
    {
        return match ($paymentTypeId) {
            'credit_card', 'debit_card', 'prepaid_card' => PaymentMethodEnum::CARD,
            'bank_transfer' => PaymentMethodEnum::PIX,
            default => null,
        };
    }
}
