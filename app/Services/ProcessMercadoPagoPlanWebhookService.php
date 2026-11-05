<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PlanCycleEnum;
use App\Enums\PlanSubscriptionStatusEnum;
use App\Helpers\PlanHelper;
use App\Jobs\RegisterLog;
use App\Models\PlanSubscription;
use App\Models\User;
use App\Models\Webhook;
use App\Wrappers\MercadoPagoWrapper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Processa as notificações webhook da assinatura do plano SaaS da própria
 * PSI4 no Mercado Pago (`subscription_preapproval` e
 * `subscription_authorized_payment`), ativando/expirando o plano do usuário.
 *
 * Substitui `ProcessPaymentKiwifyService`, preservando a mesma semântica de
 * ativação/expiração (janela rolante de 31 dias).
 */
readonly class ProcessMercadoPagoPlanWebhookService
{
    private const TOTAL_DAYS_PLAN = 31;

    public function __construct(
        private PlanSubscription $planSubscription,
        private MercadoPagoWrapper $mercadoPagoWrapper,
    ) {
    }

    /**
     * @return array{resource: array, subscription: PlanSubscription}|null dados do
     *                                                                     `authorized_payment` processado, para `ExecuteWebhookPaymentJob`
     *                                                                     montar a fatura fiscal da receita da PSI4 — `null` quando a
     *                                                                     notificação não corresponde a uma cobrança recorrente aprovada.
     */
    public function handle(Webhook $webhook): ?array
    {
        $dataId = $webhook->getDataId();

        if (! $dataId) {
            throw new \DomainException(__('payments.webhook_missing_data_id'));
        }

        return match ($webhook->getType()) {
            'subscription_preapproval' => $this->handlePreapprovalStatus($dataId),
            'subscription_authorized_payment' => $this->handleAuthorizedPayment($dataId),
            default => tap(null, fn () => Log::warning("Tópico não tratado pela assinatura do plano | {$webhook->getType()}")),
        };
    }

    /**
     * Processa a notificação `payment` do plano anual (pagamento único via
     * Checkout Pro na conta da PSI4). Idempotente: a notificação repetida de um
     * pagamento já processado não estende o plano de novo nem gera nova fatura.
     *
     * @return array{resource: array, subscription: PlanSubscription, approved: bool}|null `null` quando o
     *                                                                                     pagamento não pertence a uma assinatura anual (ex.: cobrança de paciente);
     *                                                                                     `approved` só é `true` na primeira aprovação, quando a fatura deve ser gerada.
     */
    public function handlePayment(Webhook $webhook): ?array
    {
        $paymentId = $webhook->getDataId();

        if (! $paymentId) {
            throw new \DomainException(__('payments.webhook_missing_data_id'));
        }

        $payment = $this->mercadoPagoWrapper->getPayment($paymentId);
        $externalReference = (string) ($payment['external_reference'] ?? '');

        if ($externalReference === '') {
            return null;
        }

        $subscription = $this->planSubscription->findByExternalReference($externalReference, ['id', 'user_id', 'plan', 'cycle', 'status', 'payment_id', 'external_reference']);

        if (! $subscription || $subscription->cycle !== PlanCycleEnum::ANNUAL) {
            return null;
        }

        $status = (string) ($payment['status'] ?? '');
        $resolvedPaymentId = (string) ($payment['id'] ?? $paymentId);

        if ($status === 'approved') {
            if ($subscription->payment_id === $resolvedPaymentId && $subscription->status === PlanSubscriptionStatusEnum::AUTHORIZED) {
                Log::info("Pagamento {$resolvedPaymentId} do plano anual já processado");

                return ['resource' => $payment, 'subscription' => $subscription, 'approved' => false];
            }

            $this->activateAnnual($subscription, $resolvedPaymentId);

            return ['resource' => $payment, 'subscription' => $subscription, 'approved' => true];
        }

        // Preference expirada/cancelada sem pagamento: libera um novo checkout
        if ($status === 'cancelled' && $subscription->status === PlanSubscriptionStatusEnum::PENDING) {
            $this->touchStatus($subscription, PlanSubscriptionStatusEnum::CANCELLED->value);
        }

        Log::info("Pagamento do plano anual com status '{$status}' | external_reference={$externalReference}");

        return ['resource' => $payment, 'subscription' => $subscription, 'approved' => false];
    }

    private function handlePreapprovalStatus(string $preapprovalId): ?array
    {
        $resource = $this->mercadoPagoWrapper->getSubscription($preapprovalId);
        $subscription = $this->resolveByPreapproval($resource, $preapprovalId);

        $status = PlanSubscriptionStatusEnum::tryFrom((string) ($resource['status'] ?? ''));

        match ($status) {
            PlanSubscriptionStatusEnum::AUTHORIZED => $this->activate($subscription),
            PlanSubscriptionStatusEnum::CANCELLED => $this->cancelScheduled($subscription),
            // "paused": o Mercado Pago para de cobrar (iniciado pelo pagador ou após
            // falhas de cobrança) — tratado como o antigo "subscription_late" do Kiwify.
            PlanSubscriptionStatusEnum::PAUSED => $this->rejectImmediately($subscription),
            default => $this->touchStatus($subscription, (string) ($resource['status'] ?? '')),
        };

        return null;
    }

    private function handleAuthorizedPayment(string $authorizedPaymentId): ?array
    {
        $resource = $this->mercadoPagoWrapper->getAuthorizedPayment($authorizedPaymentId);
        $preapprovalId = (string) ($resource['preapproval_id'] ?? '');

        if ($preapprovalId === '') {
            Log::warning('authorized_payment sem preapproval_id associado');

            return null;
        }

        $subscription = $this->planSubscription->findByPreapprovalId($preapprovalId, ['id', 'user_id', 'plan', 'cycle', 'status', 'preapproval_id']);

        if (! $subscription) {
            Log::warning("Assinatura não encontrada para preapproval_id {$preapprovalId}");

            return null;
        }

        $status = (string) ($resource['status'] ?? '');

        // "processed": cobrança recorrente aprovada (equivalente ao antigo
        // "order_approved"/"subscription_renewed" do Kiwify). Demais status
        // (pending, scheduled, recycled, etc.) não indicam falha definitiva —
        // o Mercado Pago cancela a assinatura via `subscription_preapproval`
        // quando esgota as tentativas, e é esse evento que rebaixa o plano.
        if ($status === 'processed') {
            $this->activate($subscription);

            return ['resource' => $resource, 'subscription' => $subscription];
        }

        Log::info("Cobrança recorrente com status '{$status}' | preapproval_id={$preapprovalId}");

        return null;
    }

    private function resolveByPreapproval(array $resource, string $preapprovalId): PlanSubscription
    {
        $subscription = $this->planSubscription->findByPreapprovalId($preapprovalId, ['id', 'user_id', 'plan', 'cycle', 'status', 'external_reference']);

        if ($subscription) {
            return $subscription;
        }

        // Primeira notificação após o checkout: ainda não tínhamos o
        // preapproval_id salvo — reconcilia pelo external_reference.
        $externalReference = (string) ($resource['external_reference'] ?? '');

        $subscription = $externalReference !== ''
            ? $this->planSubscription->findByExternalReference($externalReference, ['id', 'user_id', 'plan', 'cycle', 'status', 'external_reference'])
            : null;

        if (! $subscription) {
            Log::error("Assinatura não encontrada para preapproval_id {$preapprovalId}");

            throw new \DomainException(__('plans.subscription_not_found_for_preapproval'));
        }

        $subscription->preapproval_id = $preapprovalId;
        $subscription->save();

        return $subscription;
    }

    private function activate(PlanSubscription $subscription): void
    {
        $previousStatus = $subscription->status;
        $user = $this->getUser($subscription->user_id);

        $user->current_plan = $subscription->plan->value;
        $user->plan_activated_on = now()->toDateTime();
        $user->plan_expiration_date = now()->addDays(self::TOTAL_DAYS_PLAN)->toDateTime();

        $this->saveUser($user);
        $this->touchStatus($subscription, PlanSubscriptionStatusEnum::AUTHORIZED->value);
        $this->alertDuplicatePayment($subscription, $previousStatus);

        Log::info("Plano {$subscription->plan->value} ativado com sucesso para o usuário {$user->id}");
    }

    /**
     * Ativa (ou renova) o plano anual. Na renovação antecipada os dias são
     * somados ao vencimento atual, para o usuário não perder o tempo restante.
     */
    private function activateAnnual(PlanSubscription $subscription, string $paymentId): void
    {
        $previousStatus = $subscription->status;
        $user = $this->getUser($subscription->user_id);
        $plan = $subscription->plan->value;

        $start = now();

        if ($user->current_plan === $plan && $user->plan_expiration_date && Carbon::parse($user->plan_expiration_date)->isFuture()) {
            $start = Carbon::parse($user->plan_expiration_date);
        }

        $user->current_plan = $plan;
        $user->plan_activated_on = now()->toDateTime();
        $user->plan_expiration_date = $start->copy()->addDays(PlanHelper::annualDays($plan))->toDateTime();

        $this->saveUser($user);

        $subscription->payment_id = $paymentId;
        $subscription->status = PlanSubscriptionStatusEnum::AUTHORIZED->value;
        $subscription->renewal_notified_days = null;
        $subscription->save();

        // O alerta precisa vir antes de encerrar as anuais substituídas pela renovação
        $this->alertDuplicatePayment($subscription, $previousStatus);
        $this->planSubscription->cancelOtherAuthorizedAnnual($user->id, $subscription->id);

        Log::info("Plano anual {$plan} ativado com sucesso para o usuário {$user->id}");
    }

    /**
     * O checkout novo substitui o pendente só localmente: o link antigo continua
     * válido no Mercado Pago. Se o usuário pagar os dois, o plano é ativado (o
     * dinheiro entrou) e o `RegisterLog` dispara o alerta no Telegram para a
     * equipe estornar/cancelar o pagamento a mais.
     *
     * Não é duplicidade: renovação antecipada do plano anual (pendente -> paga
     * com outro anual vigente) nem cobranças recorrentes de uma assinatura já ativa.
     */
    private function alertDuplicatePayment(PlanSubscription $subscription, ?PlanSubscriptionStatusEnum $previousStatus): void
    {
        if ($previousStatus === PlanSubscriptionStatusEnum::AUTHORIZED) {
            return;
        }

        $other = $this->planSubscription->findOtherAuthorizedByUser($subscription->user_id, $subscription->id, ['id', 'cycle', 'payment_id', 'preapproval_id']);

        if (! $other) {
            return;
        }

        $isAnnualRenewal = $subscription->cycle === PlanCycleEnum::ANNUAL
            && $other->cycle === PlanCycleEnum::ANNUAL
            && $previousStatus !== PlanSubscriptionStatusEnum::CANCELLED;

        if ($isAnnualRenewal) {
            return;
        }

        RegisterLog::dispatchSync([
            'message' => "Possível pagamento em duplicidade do plano | usuário {$subscription->user_id}",
            'data' => [
                'user_id' => $subscription->user_id,
                'paga_agora' => ['id' => $subscription->id, 'cycle' => $subscription->cycle?->value, 'status_anterior' => $previousStatus?->value],
                'ja_vigente' => ['id' => $other->id, 'cycle' => $other->cycle?->value, 'payment_id' => $other->payment_id, 'preapproval_id' => $other->preapproval_id],
            ],
            'route' => 'ProcessMercadoPagoPlanWebhookService->alertDuplicatePayment',
            'method' => 'WEBHOOK',
        ]);
    }

    private function cancelScheduled(PlanSubscription $subscription): void
    {
        // Só uma assinatura que chegou a ser paga mexe no plano; checkout pendente
        // ou substituído por outra opção que é cancelado não pode rebaixar o usuário.
        $wasAuthorized = $subscription->status === PlanSubscriptionStatusEnum::AUTHORIZED;

        $user = $this->getUser($subscription->user_id);
        $this->touchStatus($subscription, PlanSubscriptionStatusEnum::CANCELLED->value);

        if (! $wasAuthorized) {
            Log::info("Cancelamento de assinatura que não estava ativa. Plano mantido | usuário {$user->id}");

            return;
        }

        if ($user->current_plan === \App\Enums\PlanUserEnum::FREE->value) {
            Log::warning("Plano já está FREE. Nenhuma ação necessária | usuário {$user->id}");

            return;
        }

        $daysUsed = Carbon::parse($user->plan_activated_on)->diffInDays(now());

        if ($daysUsed >= self::TOTAL_DAYS_PLAN) {
            $user->current_plan = \App\Enums\PlanUserEnum::FREE->value;
            $user->plan_expiration_date = null;
            $user->plan_activated_on = null;

            Log::info("Plano expirado. Alterado para FREE | usuário {$user->id}");
        } else {
            $remainingDays = self::TOTAL_DAYS_PLAN - $daysUsed;

            $user->plan_expiration_date = Carbon::parse($user->plan_activated_on)->addDays($remainingDays)->toDateTime();

            Log::info("Cancelamento programado para fim do ciclo | usuário {$user->id}");
        }

        $this->saveUser($user);
    }

    private function rejectImmediately(PlanSubscription $subscription): void
    {
        $user = $this->getUser($subscription->user_id);
        $this->touchStatus($subscription, PlanSubscriptionStatusEnum::PAUSED->value);

        if ($user->current_plan === \App\Enums\PlanUserEnum::FREE->value) {
            Log::warning("Plano já está FREE. Nenhuma ação necessária | usuário {$user->id}");

            return;
        }

        $user->current_plan = \App\Enums\PlanUserEnum::FREE->value;
        $user->plan_expiration_date = null;
        $user->plan_activated_on = null;

        $this->saveUser($user);

        Log::info("Plano alterado para FREE com sucesso | usuário {$user->id}");
    }

    private function touchStatus(PlanSubscription $subscription, string $status): void
    {
        $enum = PlanSubscriptionStatusEnum::tryFrom($status);

        if ($enum && $subscription->status !== $enum) {
            $subscription->status = $enum->value;
            $subscription->save();
        }
    }

    private function getUser(int $userId): User
    {
        $user = User::query()
            ->select(['id', 'current_plan', 'plan_expiration_date', 'plan_activated_on'])
            ->find($userId);

        if (! $user) {
            Log::error("Usuário {$userId} não encontrado");

            throw new \DomainException(__('plans.user_not_found'));
        }

        return $user;
    }

    private function saveUser(User $user): void
    {
        if (! $user->save()) {
            Log::error("Falha ao atualizar usuário ID {$user->id}");

            throw new \DomainException(__('plans.user_update_failed'));
        }
    }
}
