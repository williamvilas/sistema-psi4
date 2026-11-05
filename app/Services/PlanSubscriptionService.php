<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethodEnum;
use App\Enums\PlanCycleEnum;
use App\Enums\PlanSubscriptionStatusEnum;
use App\Enums\PlanUserEnum;
use App\Helpers\CacheHelper;
use App\Helpers\MercadoPagoFeeHelper;
use App\Helpers\PlanHelper;
use App\Mail\PlanCancellationMail;
use App\Mail\PlanRenewalMail;
use App\Models\PlanSubscription;
use App\Models\User;
use App\Wrappers\MercadoPagoWrapper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

readonly class PlanSubscriptionService
{
    /** Dias de antecedência (e marcos de aviso) em que a renovação do plano anual fica disponível. */
    public const RENEWAL_MILESTONES = [0, 3, 15];

    /** A preference do plano anual expira em 1 dia: depois disso o link não funciona mais. */
    private const ANNUAL_CHECKOUT_TTL_DAYS = 1;

    /** Limites do plano exibidos na tela de planos => slug do texto quando ilimitado (-1). */
    private const LIMIT_FEATURES = [
        'total_agenda' => 'agenda_unlimited',
        'total_patients' => 'patients_unlimited',
        'total_medical_records' => 'medical_records_unlimited',
        'total_documents_issued' => 'documents_issued_unlimited',
        'total_scales' => 'scales_unlimited',
        'total_payment_orders' => 'payment_orders_unlimited',
        'total_whatsapp_manual' => 'whatsapp_manual_unlimited',
        'total_whatsapp_auto' => 'whatsapp_auto_unlimited',
        'total_leads' => 'leads_unlimited',
    ];

    public function __construct(
        private PlanSubscription $planSubscription,
        private MercadoPagoWrapper $mercadoPagoWrapper,
    ) {
    }

    /**
     * Cria o checkout do plano pago escolhido. O valor vem sempre de
     * `config('plans.plans')` — nunca do request.
     *
     * - `monthly`: assinatura recorrente (`/preapproval`), cartão cadastrado
     *   na página hospedada do Mercado Pago.
     * - `annual`: pagamento único (Checkout Pro) em até N vezes sem juros no
     *   cartão ou à vista no PIX, sem renovação automática.
     *
     * @return string init_point para onde o navegador deve ser redirecionado
     */
    public function checkout(string $plan, User $user, string $cycle = 'monthly', ?string $paymentMethod = null): string
    {
        $planEnum = PlanUserEnum::tryFrom($plan);
        $planConfig = config('plans.plans.'.$plan);

        if (! $planEnum || ! is_array($planConfig)) {
            throw new \DomainException(__('plans.plan_not_found'));
        }

        if (empty($planConfig['price'])) {
            throw new \DomainException(__('plans.plan_free_no_checkout'));
        }

        $cycleEnum = PlanCycleEnum::tryFrom($cycle);

        if (! $cycleEnum) {
            throw new \DomainException(__('plans.cycle_invalid'));
        }

        // Lock por usuário: duplo clique não cria dois checkouts em paralelo
        return CacheHelper::lock('plan_checkout:'.$user->id, 30, 10, fn () => $cycleEnum === PlanCycleEnum::ANNUAL
            ? $this->checkoutAnnual($planEnum, $user, $paymentMethod)
            : $this->checkoutMonthly($planEnum, $planConfig, $user));
    }

    /**
     * Opções de ciclo disponíveis para o usuário na página de escolha.
     *
     * @return array{monthly: bool, annual: bool, renewal: bool}
     *
     * @throws \DomainException quando o usuário não pode contratar/renovar agora
     */
    public function getCheckoutOptions(string $plan, User $user): array
    {
        $planEnum = PlanUserEnum::tryFrom($plan);
        $planConfig = config('plans.plans.'.$plan);

        if (! $planEnum || ! is_array($planConfig)) {
            throw new \DomainException(__('plans.plan_not_found'));
        }

        if (empty($planConfig['price'])) {
            throw new \DomainException(__('plans.plan_free_no_checkout'));
        }

        // Renovação cancelada: nada de nova assinatura até o vencimento (evita pagamento em duplicidade)
        if ($this->planSubscription->findScheduledCancelByUser($user->id, ['id'])) {
            throw new \DomainException(__('plans.cancel_renewal_blocked'));
        }

        $annualAvailable = PlanHelper::annualConfig($plan) !== null;

        if ($user->current_plan === $planEnum->value) {
            // Plano anual vigente perto do vencimento: só a renovação anual é liberada
            if ($annualAvailable && $this->planSubscription->findActiveAnnualByUser($user->id, ['id']) && $this->isInRenewalWindow($user)) {
                return ['monthly' => false, 'annual' => true, 'renewal' => true];
            }

            throw new \DomainException(__('plans.plan_change_unavailable'));
        }

        // Um pagamento pendente não bloqueia: o usuário pode desistir e escolher outra opção
        return ['monthly' => true, 'annual' => $annualAvailable, 'renewal' => false];
    }

    /**
     * Checkout pendente a ser retomado na tela de planos: só o último registro
     * do usuário, e só se ainda estiver pendente e com o link válido (a
     * preference anual expira).
     */
    public function getPendingCheckout(User $user): ?PlanSubscription
    {
        $latest = $this->planSubscription->findLatestByUser($user->id, ['id', 'plan', 'cycle', 'payment_method', 'status', 'init_point', 'created_at']);

        if (! $latest || $latest->status !== PlanSubscriptionStatusEnum::PENDING || ! $latest->init_point) {
            return null;
        }

        if ($latest->cycle === PlanCycleEnum::ANNUAL && $latest->created_at->lte(now()->subDays(self::ANNUAL_CHECKOUT_TTL_DAYS))) {
            return null;
        }

        return $latest;
    }

    /**
     * Planos prontos para a tela de planos: preços, recursos alinhados entre os
     * planos (o que um plano não tem aparece como não incluso) e a ação
     * disponível para o usuário em cada um.
     *
     * @return array<int, array{key: string, name: string, tagline: string, current: bool, emphasis: bool, price: float, annual: ?array, features: array, action: ?string, can_cancel_renewal: bool, renewal_cancelled: bool, expiration_date: ?string}>
     */
    public function getPlansForDisplay(User $user): array
    {
        $plans = config('plans.plans');

        $currentPlan = $user->current_plan;
        $cancelable = $this->getCancelableSubscription($user);
        $scheduled = $this->getScheduledCancellation($user);
        // Recursos de todos os planos, para cada card listar as mesmas linhas
        $extras = array_values(array_unique(array_merge([], ...array_column($plans, 'extras'))));
        $limits = array_filter(self::LIMIT_FEATURES, fn (string $limit) => array_any($plans, fn (array $plan) => isset($plan[$limit])), ARRAY_FILTER_USE_KEY);

        $display = [];

        foreach ($plans as $key => $plan) {
            $isCurrent = $currentPlan === $key;
            $annual = PlanHelper::annualConfig($key);

            $display[] = [
                'key' => $key,
                'name' => __('plans.'.$key),
                'tagline' => __('plans.taglines.'.$key),
                'current' => $isCurrent,
                'emphasis' => (bool) ($plan['emphasis'] ?? false),
                'price' => (float) $plan['price'],
                'annual' => $annual ? [
                    'discount' => PlanHelper::annualDiscount($key),
                    'monthly_equivalent' => PlanHelper::annualMonthlyEquivalent($key),
                    'total' => PlanHelper::annualTotal($key),
                ] : null,
                'features' => $this->buildFeatures($plan, $limits, $extras),
                'action' => $this->resolveAction($key, $plan, $isCurrent, $annual !== null, $user),
                'can_cancel_renewal' => $isCurrent && $cancelable?->plan->value === $key,
                'renewal_cancelled' => $isCurrent && $scheduled?->plan->value === $key,
                'expiration_date' => $isCurrent && $user->plan_expiration_date ? Carbon::parse($user->plan_expiration_date)->format('d/m/Y') : null,
            ];
        }

        return $display;
    }

    public function canRenewAnnual(string $plan, User $user): bool
    {
        try {
            return $this->getCheckoutOptions($plan, $user)['renewal'];
        } catch (\DomainException) {
            return false;
        }
    }

    /**
     * Rebaixa para o plano grátis os usuários cujo plano anual venceu — o
     * anual é pagamento único, então não existe cancelamento vindo do
     * Mercado Pago para expirá-lo.
     *
     * @return int total de planos expirados
     */
    public function expireAnnualPlans(): int
    {
        $total = 0;

        foreach ($this->planSubscription->getExpiredAnnual(now(), ['id', 'user_id']) as $subscription) {
            $total += $this->expireSubscription($subscription, 'anual');
        }

        return $total;
    }

    /**
     * Rebaixa para o plano grátis os usuários que cancelaram a renovação do
     * plano mensal e cujo ciclo pago já terminou. O Mercado Pago já parou de
     * cobrar (cancelamento feito em `cancelRenewal`), então nada mais o expira.
     *
     * @return int total de planos expirados
     */
    public function expireCancelledMonthlyPlans(): int
    {
        $total = 0;

        foreach ($this->planSubscription->getExpiredCancelledMonthly(now(), ['id', 'user_id']) as $subscription) {
            $total += $this->expireSubscription($subscription, 'mensal cancelado');
        }

        return $total;
    }

    /**
     * Assinatura que o usuário pode ter a renovação cancelada: vigente (paga) e
     * ainda sem cancelamento agendado.
     */
    public function getCancelableSubscription(User $user): ?PlanSubscription
    {
        $subscription = $this->planSubscription->findAuthorizedByUser($user->id, ['id', 'plan', 'cycle', 'cancel_at_period_end_at']);

        return $subscription && ! $subscription->cancel_at_period_end_at ? $subscription : null;
    }

    public function getScheduledCancellation(User $user): ?PlanSubscription
    {
        return $this->planSubscription->findScheduledCancelByUser($user->id, ['id', 'plan', 'cycle', 'cancel_at_period_end_at']);
    }

    /**
     * Cancela a renovação do plano: o usuário mantém o acesso até
     * `plan_expiration_date` e depois volta para o plano grátis (rotina de expiração).
     *
     * - `monthly`: cancela a assinatura recorrente no Mercado Pago (para de cobrar).
     * - `annual`: pagamento único, não há o que cancelar no Mercado Pago.
     *
     * @return string data de término do plano (d/m/Y)
     */
    public function cancelRenewal(User $user): string
    {
        // Lock por usuário: duplo clique não cancela duas vezes
        return CacheHelper::lock('plan_cancel_renewal:'.$user->id, 30, 10, function () use ($user) {
            $subscription = $this->planSubscription->findAuthorizedByUser($user->id, ['id', 'cycle', 'preapproval_id', 'cancel_at_period_end_at']);

            if (! $subscription) {
                throw new \DomainException(__('plans.cancel_renewal_unavailable'));
            }

            if ($subscription->cancel_at_period_end_at) {
                throw new \DomainException(__('plans.cancel_renewal_already_scheduled'));
            }

            if ($subscription->cycle === PlanCycleEnum::MONTHLY) {
                if (! $subscription->preapproval_id) {
                    throw new \DomainException(__('plans.cancel_renewal_unavailable'));
                }

                // A API primeiro: se falhar nada é gravado e o usuário pode tentar de novo
                $this->mercadoPagoWrapper->cancelSubscription($subscription->preapproval_id);
            }

            $subscription->update(['cancel_at_period_end_at' => now()]);

            $expirationDate = Carbon::parse($user->plan_expiration_date)->format('d/m/Y');

            Mail::to($user->email)->send(new PlanCancellationMail(name: $user->name, expirationDate: $expirationDate));

            return $expirationDate;
        });
    }

    /**
     * Avisa por e-mail os usuários com plano anual perto de vencer, uma vez
     * por marco (15, 3 e 0 dias antes). Marcos já passados não são reenviados,
     * e se o comando ficar dias sem rodar só o marco mais próximo é enviado.
     *
     * @return int total de e-mails enviados
     */
    public function notifyAnnualRenewals(): int
    {
        $today = now()->startOfDay();
        $limit = now()->addDays(max(self::RENEWAL_MILESTONES))->endOfDay();
        $total = 0;

        foreach ($this->planSubscription->getAnnualExpiringUntil($limit, ['id', 'user_id', 'renewal_notified_days']) as $subscription) {
            $user = $subscription->user;

            if (! $user || ! $user->plan_expiration_date) {
                continue;
            }

            $expiration = Carbon::parse($user->plan_expiration_date);
            $daysLeft = max(0, (int) $today->diffInDays($expiration->copy()->startOfDay(), false));

            $milestone = collect(self::RENEWAL_MILESTONES)->first(fn (int $days) => $daysLeft <= $days);

            if ($milestone === null) {
                continue;
            }

            if ($subscription->renewal_notified_days !== null && $subscription->renewal_notified_days <= $milestone) {
                continue;
            }

            Mail::to($user->email)->send(new PlanRenewalMail(
                name: $user->name,
                daysLeft: $daysLeft,
                expirationDate: $expiration->format('d/m/Y'),
                url: route('plans.index'),
            ));

            $subscription->update(['renewal_notified_days' => $milestone]);
            $total++;
        }

        return $total;
    }

    private function checkoutMonthly(PlanUserEnum $planEnum, array $planConfig, User $user): string
    {
        if ($user->current_plan === $planEnum->value) {
            throw new \DomainException(__('plans.plan_free_no_checkout'));
        }

        $externalReference = Str::uuid()->toString();

        $result = $this->mercadoPagoWrapper->createSubscription([
            'reason' => 'Plano '.__('plans.'.$planEnum->value).' - PSI4',
            'external_reference' => $externalReference,
            'payer_email' => $user->email,
            'auto_recurring' => [
                'frequency' => 1,
                'frequency_type' => 'months',
                'transaction_amount' => (float) $planConfig['price'],
                'currency_id' => 'BRL',
            ],
            'back_url' => route('plans.index'),
        ]);

        $subscription = $this->planSubscription->create([
            'user_id' => $user->id,
            'plan' => $planEnum->value,
            'cycle' => PlanCycleEnum::MONTHLY->value,
            'external_reference' => $externalReference,
            'preapproval_id' => $result['id'],
            'status' => PlanSubscriptionStatusEnum::tryFrom($result['status'])?->value ?? PlanSubscriptionStatusEnum::PENDING->value,
            'init_point' => $result['init_point'],
        ]);

        $this->planSubscription->cancelPendingByUser($user->id, (int) $subscription->id);

        return $result['init_point'];
    }

    private function checkoutAnnual(PlanUserEnum $planEnum, User $user, ?string $paymentMethod): string
    {
        $method = PaymentMethodEnum::tryFrom((string) $paymentMethod);

        if (! $method) {
            throw new \DomainException(__('plans.payment_method_invalid'));
        }

        if (! $this->getCheckoutOptions($planEnum->value, $user)['annual']) {
            throw new \DomainException(__('plans.annual_not_available'));
        }

        $plan = $planEnum->value;
        $isCard = $method === PaymentMethodEnum::CARD;
        $amount = $isCard ? PlanHelper::annualTotal($plan) : PlanHelper::annualPixTotal($plan);
        $externalReference = Str::uuid()->toString();
        $successUrl = route('plans.index');

        $result = $this->mercadoPagoWrapper->createPreference([
            'items' => [[
                'title' => 'Plano '.__('plans.'.$plan).' anual - PSI4',
                'quantity' => 1,
                'unit_price' => $amount,
                'currency_id' => 'BRL',
            ]],
            'payer' => ['email' => $user->email],
            'external_reference' => $externalReference,
            'notification_url' => route('webhook.mercadopago'),
            'payment_methods' => [
                'excluded_payment_types' => PaymentMethodEnum::excludedMercadoPagoPaymentTypes([$method->value]),
                'installments' => $isCard ? PlanHelper::annualInstallments($plan) : 1,
            ],
            'statement_descriptor' => 'PSI4',
            'back_urls' => ['success' => $successUrl, 'failure' => $successUrl, 'pending' => $successUrl],
            'expires' => true,
            'expiration_date_to' => now()->addDays(self::ANNUAL_CHECKOUT_TTL_DAYS)->format('Y-m-d\TH:i:s.vP'),
            // O Mercado Pago só aceita `auto_return` quando o back_url de sucesso é HTTPS.
            ...(Str::startsWith($successUrl, 'https://') ? ['auto_return' => 'approved'] : []),
        ]);

        $subscription = $this->planSubscription->create([
            'user_id' => $user->id,
            'plan' => $planEnum->value,
            'cycle' => PlanCycleEnum::ANNUAL->value,
            'payment_method' => $method->value,
            'external_reference' => $externalReference,
            'status' => PlanSubscriptionStatusEnum::PENDING->value,
            'init_point' => $result['init_point'],
        ]);

        $this->planSubscription->cancelPendingByUser($user->id, (int) $subscription->id);

        return $result['init_point'];
    }

    /**
     * Rebaixa o usuário da assinatura para o plano grátis e encerra a assinatura.
     *
     * @return int 1 quando o plano do usuário foi rebaixado, 0 caso contrário
     */
    private function expireSubscription(PlanSubscription $subscription, string $context): int
    {
        return DB::transaction(function () use ($subscription, $context) {
            $user = $subscription->user;
            $expired = 0;

            if ($user && $user->current_plan !== PlanUserEnum::FREE->value) {
                $user->current_plan = PlanUserEnum::FREE->value;
                $user->plan_expiration_date = null;
                $user->plan_activated_on = null;
                $user->save();

                $expired = 1;
                Log::info("Plano {$context} expirado. Alterado para FREE | usuário {$user->id}");
            }

            $subscription->update(['status' => PlanSubscriptionStatusEnum::CANCELLED->value]);

            return $expired;
        });
    }

    /** @return array<int, array{label: string, included: bool, note?: string}> */
    private function buildFeatures(array $plan, array $limits, array $extras): array
    {
        $features = [];

        foreach ($limits as $limit => $unlimited) {
            // Limite que outro plano tem e este não: exibido como não incluso
            $total = $plan[$limit] ?? 0;

            $features[] = [
                'label' => $total > 0
                    ? trans_choice('plans.'.$limit, $total, ['total' => $total])
                    : __('plans.'.$unlimited),
                'included' => $total !== 0,
            ];
        }

        if (isset($plan['payment_fee_percent'])) {
            // Sem taxa do PSI4: avisa, depois de cada taxa, que ela é só do Mercado Pago
            $note = (float) $plan['payment_fee_percent'] <= 0 ? ['note' => __('plans.payment_fee_mercado_pago_only')] : [];

            foreach (MercadoPagoFeeHelper::planFeeLabels((float) $plan['payment_fee_percent']) as $label) {
                $features[] = ['label' => $label, 'included' => true] + $note;
            }
        }

        foreach ($extras as $extra) {
            $features[] = [
                // Slug de `plans.features.*`; texto livre no config é exibido como está
                'label' => Lang::has('plans.features.'.$extra) ? __('plans.features.'.$extra, ['model' => __('plans.model_ia')]) : $extra,
                'included' => in_array($extra, $plan['extras'] ?? [], true),
            ];
        }

        return $features;
    }

    /** Ação do card: `renew` (anual perto do vencimento), `current`, `subscribe` (plano pago) ou nenhuma. */
    private function resolveAction(string $key, array $plan, bool $isCurrent, bool $hasAnnual, User $user): ?string
    {
        if ($isCurrent) {
            return $hasAnnual && $this->canRenewAnnual($key, $user) ? 'renew' : 'current';
        }

        return (float) $plan['price'] > 0 ? 'subscribe' : null;
    }

    private function isInRenewalWindow(User $user): bool
    {
        if (! $user->plan_expiration_date) {
            return false;
        }

        return Carbon::parse($user->plan_expiration_date)->lte(now()->addDays(max(self::RENEWAL_MILESTONES))->endOfDay());
    }
}
