<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FeatureUsageEnum;
use App\Enums\PaginateCacheContextEnum;
use App\Enums\PaymentAccountsStatusEnum;
use App\Enums\PaymentGatewayEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentOrderIntegrationStatusEnum;
use App\Enums\PaymentOrderStatusEnum;
use App\Enums\PlanUserEnum;
use App\Helpers\PaginateCache;
use App\Helpers\PlanHelper;
use App\Jobs\CreateMercadoPagoChargeJob;
use App\Jobs\RegisterLog;
use App\Models\Patients;
use App\Models\PaymentAccount;
use App\Models\PaymentOrder;
use App\Wrappers\MercadoPagoConnectWrapper;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

readonly class PaymentOrderService
{
    public function __construct(
        private PaymentOrder $paymentOrder,
        private Patients $patients,
        private PaymentAccount $paymentAccount,
        private MercadoPagoConnectWrapper $mercadoPagoConnectWrapper,
        private PaymentAccountService $paymentAccountService,
        private HealthReceiptService $healthReceiptService,
        private PlanLimitService $planLimitService = new PlanLimitService,
    ) {
    }

    public function paginate(array $params, int $userId, int $limit, ?int $patientId = null): LengthAwarePaginator
    {
        $status = $params['status'] ?? null;
        $today = today()->toDateString();

        return PaginateCache::remember(
            context: PaginateCacheContextEnum::PAYMENTS,
            userId: $userId,
            params: ['status' => $status, 'patient_id' => $patientId, 'limit' => $limit, 'today' => $today],
            callback: fn () => $this->paymentOrder->paginateByUserWithPatient(
                userId: $userId,
                cols: [
                    'id',
                    'uuid',
                    'patient_id',
                    'amount',
                    'description',
                    'status',
                    'integration_status',
                    'payment_methods',
                    'payment_method',
                    'checkout_url',
                    'due_date',
                    'paid_at',
                    'created_at',
                ],
                status: $status,
                patientId: $patientId,
                today: $today,
                perPage: $limit,
            )->withQueryString(),
        );
    }

    /**
     * Resumo financeiro do topo da listagem: a receber (pendentes no prazo), recebido no mês
     * (mesmo critério do dashboard) e vencidas. Cacheado junto com a listagem de pagamentos,
     * então é invalidado a cada alteração de ordem.
     *
     * @return array{receivable: array{count: int, total: float}, received_month: array{count: int, total: float}, overdue: array{count: int, total: float}}
     */
    public function summary(int $userId, string $timezone): array
    {
        $now = now($timezone);
        $today = $now->toDateString();

        return PaginateCache::remember(
            context: PaginateCacheContextEnum::PAYMENTS,
            userId: $userId,
            params: ['scope' => 'summary', 'today' => $today],
            callback: fn () => [
                'receivable' => $this->paymentOrder->summarizePending($userId, $today),
                'received_month' => $this->paymentOrder->summarizeByStatusAndPeriod(
                    $userId,
                    PaymentOrderStatusEnum::PAID,
                    $now->copy()->startOfMonth()->utc(),
                    $now->copy()->utc(),
                ),
                'overdue' => $this->paymentOrder->summarizeOverdue($userId, $today),
            ],
        );
    }

    public function hasOrders(int $userId): bool
    {
        return $this->paymentOrder->existsByUser($userId);
    }

    public function details(int $id, int $userId): ?PaymentOrder
    {
        $cols = [
            'id',
            'uuid',
            'patient_id',
            'amount',
            'description',
            'status',
            'integration_status',
            'payment_methods',
            'payment_method',
            'checkout_url',
            'due_date',
            'paid_at',
            'created_at',
        ];

        return $this->paymentOrder->findByIdAndUserWithPatient(
            id: $id,
            userId: $userId,
            cols: $cols,
            patientCols: ['id', 'name', 'phone', 'email', 'document', 'deleted_at'],
        );
    }

    public function store(array $data, int $userId): PaymentOrder
    {
        $patient = $this->patients->newQuery()
            ->select('id')
            ->where('id', $data['patient_id'])
            ->where('user_id', $userId)
            ->first();

        if (! $patient) {
            throw new \DomainException(__('payments.patient_not_found'));
        }

        $this->assertAccountReady($userId);

        // Uso do plano e registro juntos; o job só é despachado depois de o registro existir
        $order = DB::transaction(function () use ($data, $userId, $patient) {
            $this->planLimitService->consume(userId: $userId, feature: FeatureUsageEnum::CREATE_PAYMENT_ORDER);

            return $this->paymentOrder->create([
                'uuid' => Str::uuid()->toString(),
                'user_id' => $userId,
                'patient_id' => $patient->id,
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'status' => PaymentOrderStatusEnum::PENDING->value,
                'integration_status' => PaymentOrderIntegrationStatusEnum::PENDING->value,
                'payment_methods' => $data['payment_methods'],
                'due_date' => $data['due_date'] ?? null,
            ]);
        });

        CreateMercadoPagoChargeJob::dispatch($order->id);

        return $order;
    }

    /**
     * Impede a criação de uma ordem quando o terapeuta ainda não conectou (ou
     * precisa reconectar) sua conta Mercado Pago — evita gerar um pedido que
     * ficaria pendente de integração indefinidamente.
     */
    private function assertAccountReady(int $userId): void
    {
        $account = $this->paymentAccount->findByUserAndProvider($userId, PaymentGatewayEnum::MERCADO_PAGO->value, ['id', 'status']);

        if (! $account || $account->status === PaymentAccountsStatusEnum::PENDING) {
            throw new \DomainException(__('payments.account_not_connected'));
        }

        if (in_array($account->status, [PaymentAccountsStatusEnum::NEEDS_REAUTH, PaymentAccountsStatusEnum::ERROR], true)) {
            throw new \DomainException(__('payments.account_needs_reauth'));
        }
    }

    /**
     * Cobrança já integrada também é atualizada no Mercado Pago (mesma preference,
     * mesmo link); se o Mercado Pago recusar, a alteração local é desfeita.
     * Cobrança com falha na integração volta para a fila para gerar o link de novo.
     */
    public function update(int $id, array $data, int $userId): void
    {
        $order = $this->paymentOrder->findByIdAndUserWithPatient(
            id: $id,
            userId: $userId,
            cols: ['id', 'uuid', 'user_id', 'patient_id', 'status', 'integration_status', 'external_id'],
            patientCols: ['id', 'email'],
        );

        if (! $order) {
            throw new \DomainException(__('payments.not_found'));
        }

        if (! $order->isEditable()) {
            throw new \DomainException(__('payments.cannot_edit'));
        }

        $this->assertAccountReady($userId);

        $isIntegrated = $order->integration_status === PaymentOrderIntegrationStatusEnum::INTEGRATED;
        $isError = $order->integration_status === PaymentOrderIntegrationStatusEnum::ERROR;

        $payload = [
            'amount' => $data['amount'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'payment_methods' => json_encode($data['payment_methods']),
        ];

        if ($isError) {
            $payload['integration_status'] = PaymentOrderIntegrationStatusEnum::PENDING->value;
        }

        try {
            // Token obtido fora da transação: a renovação grava na conta e não deve ser desfeita
            $accessToken = $isIntegrated ? $this->getSellerAccessToken($userId) : null;

            DB::transaction(function () use ($id, $userId, $payload, $data, $order, $accessToken) {
                $this->paymentOrder->updateByIdAndUser(id: $id, userId: $userId, data: $payload);

                if ($accessToken === null) {
                    return;
                }

                $order->fill([
                    'amount' => $data['amount'],
                    'description' => $data['description'] ?? null,
                    'due_date' => $data['due_date'] ?? null,
                    'payment_methods' => $data['payment_methods'],
                ]);

                $this->mercadoPagoConnectWrapper->updatePreference(
                    sellerAccessToken: $accessToken,
                    preferenceId: (string) $order->external_id,
                    body: $this->buildPreferenceBody($order),
                );
            });
        } catch (\DomainException $e) {
            throw $e;
        } catch (\Throwable $t) {
            RegisterLog::dispatchSync([
                'data' => ['payment_order_id' => $id],
                'message' => $t->getMessage(),
                'method' => 'PaymentOrderService->update',
            ]);

            throw new \DomainException(__('messages.failed_update'));
        }

        if ($isError) {
            CreateMercadoPagoChargeJob::dispatch($id);
        }
    }

    /**
     * Corpo da preference do Checkout Pro, usado tanto na criação (job) quanto
     * na edição da cobrança. Espera a ordem com `patient` (e-mail) carregado.
     */
    public function buildPreferenceBody(PaymentOrder $order): array
    {
        $email = trim((string) $order->patient?->email);

        $backUrls = [
            'success' => route('payments.confirmation', ['status' => 'success']),
            'failure' => route('payments.confirmation', ['status' => 'failure']),
            'pending' => route('payments.confirmation', ['status' => 'pending']),
        ];

        $fee = PlanHelper::paymentFee($order->user?->current_plan ?: PlanUserEnum::FREE->value, (float) $order->amount);

        return [
            'items' => [[
                'title' => Str::limit(
                    __('payments.item_title', ['name' => $order->user?->name]),
                    60,
                    '',
                ),
                'quantity' => 1,
                'unit_price' => (float) $order->amount,
                'currency_id' => 'BRL',
                'description' => Str::limit(
                    (string) ($order->description ?: __('payments.item_description', ['id' => $order->id])),
                    100,
                    '',
                ),
            ]],
            // E-mail vazio é rejeitado pelo Mercado Pago (400): só envia quando existir
            ...($email !== '' ? ['payer' => ['email' => $email]] : []),
            'external_reference' => $order->uuid,
            'notification_url' => route('webhook.mercadopago'),
            'payment_methods' => [
                'excluded_payment_types' => PaymentMethodEnum::excludedMercadoPagoPaymentTypes((array) $order->payment_methods),
            ],
            'statement_descriptor' => 'PSI4',
            // Taxa do PSI4 sobre a cobrança (varia por plano); o Mercado Pago repassa à conta do app
            ...($fee > 0 ? ['marketplace_fee' => $fee] : []),
            'back_urls' => $backUrls,
            ...$this->getExpirationFields($order->due_date),
            // O Mercado Pago só aceita `auto_return` quando o back_url de sucesso é HTTPS.
            ...(Str::startsWith($backUrls['success'], 'https://') ? ['auto_return' => 'approved'] : []),
        ];
    }

    /**
     * A cobrança expira no fim do dia do vencimento. O Mercado Pago exige
     * ISO 8601 com milissegundos e fuso (ex.: 2026-10-05T23:59:59.000-03:00)
     * e rejeita datas no passado, então vencimentos já vencidos não expiram.
     * `expires: false` explícito remove a expiração ao editar uma preference
     * que tinha vencimento.
     *
     * @return array{expires: bool, expiration_date_to?: string}
     */
    private function getExpirationFields(?Carbon $dueDate): array
    {
        // O app roda em UTC: sem fixar o fuso, o fim do dia sairia 23:59 UTC (20:59 em Brasília)
        $expiration = $dueDate
            ? Carbon::parse($dueDate->toDateString(), 'America/Sao_Paulo')->endOfDay()
            : null;

        if (! $expiration || $expiration->isPast()) {
            return ['expires' => false];
        }

        return [
            'expires' => true,
            'expiration_date_to' => $expiration->format('Y-m-d\TH:i:s.vP'),
        ];
    }

    /**
     * Sem conta integrada (com token) não há como atualizar a cobrança no Mercado Pago.
     */
    private function getSellerAccessToken(int $userId): string
    {
        $account = $this->paymentAccount->findIntegratedByUser($userId, ['id', 'access_token', 'refresh_token', 'token_expires_at']);

        if (! $account) {
            throw new \DomainException(__('payments.account_needs_reauth'));
        }

        return $this->paymentAccountService->getValidAccessToken($account);
    }

    /**
     * Estorna no Mercado Pago uma ordem já paga, usando a conta conectada do
     * terapeuta. Só o pagamento aprovado pode ser estornado; a ordem passa a
     * `refunded` assim que o Mercado Pago confirma (o webhook reconcilia os
     * demais casos).
     */
    public function refund(int $id, int $userId): void
    {
        $order = $this->paymentOrder->findByIdAndUser(
            id: $id,
            userId: $userId,
            cols: ['id', 'uuid', 'user_id', 'status', 'external_id', 'integration_status'],
        );

        if (! $order) {
            throw new \DomainException(__('payments.not_found'));
        }

        if ($order->status !== PaymentOrderStatusEnum::PAID || ! $order->external_id) {
            throw new \DomainException(__('payments.cannot_refund'));
        }

        $account = $this->paymentAccount->findIntegratedByUser($userId, ['id', 'access_token', 'refresh_token', 'token_expires_at']);

        if (! $account) {
            throw new \DomainException(__('payments.account_needs_reauth'));
        }

        try {
            $accessToken = $this->paymentAccountService->getValidAccessToken($account);

            $this->mercadoPagoConnectWrapper->refundPayment(
                sellerAccessToken: $accessToken,
                paymentId: (string) $order->external_id,
                idempotencyKey: 'refund-'.$order->uuid,
            );
        } catch (\DomainException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new \DomainException(__('payments.refund_failed'));
        }

        DB::transaction(function () use ($id, $userId) {
            $this->paymentOrder->updateByIdAndUser(id: $id, userId: $userId, data: [
                'status' => PaymentOrderStatusEnum::REFUNDED->value,
            ]);

            $this->healthReceiptService->handleRefund($id);
        });
    }

    public function delete(array $IDs, int $userId): void
    {
        $ids = array_filter(Arr::map($IDs, function ($id) {
            try {
                return decrypt($id);
            } catch (\Throwable $e) {
                return null;
            }
        }));

        if (empty($ids)) {
            throw new \DomainException(__('payments.not_found'));
        }

        try {
            DB::transaction(function () use ($ids, $userId) {
                foreach (array_chunk($ids, 10) as $chunk) {
                    if (! $this->paymentOrder->deleteInBatch(id: $chunk, userId: $userId)) {
                        throw new \Exception('failed_delete');
                    }
                }
            });
        } catch (\Throwable $t) {
            RegisterLog::dispatchSync([
                'data' => $IDs,
                'message' => $t->getMessage(),
                'method' => 'PaymentOrderService->delete',
            ]);

            throw new \DomainException(__('messages.failed_delete'));
        }
    }
}
