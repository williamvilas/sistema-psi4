<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethodEnum;
use App\Enums\PlanCycleEnum;
use App\Enums\PlanSubscriptionStatusEnum;
use App\Enums\PlanUserEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanSubscription extends Model
{
    protected $table = 'plan_subscriptions';

    protected $fillable = [
        'user_id',
        'plan',
        'cycle',
        'payment_method',
        'external_reference',
        'preapproval_id',
        'payment_id',
        'status',
        'init_point',
        'renewal_notified_days',
        'cancel_at_period_end_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'plan' => PlanUserEnum::class,
        'cycle' => PlanCycleEnum::class,
        'payment_method' => PaymentMethodEnum::class,
        'renewal_notified_days' => 'integer',
        'cancel_at_period_end_at' => 'datetime',
        'status' => PlanSubscriptionStatusEnum::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function findByExternalReference(string $externalReference, array $cols = ['*']): ?self
    {
        return PlanSubscription::query()->select($cols)->where('external_reference', $externalReference)->first();
    }

    public function findByPreapprovalId(string $preapprovalId, array $cols = ['*']): ?self
    {
        return PlanSubscription::query()->select($cols)->where('preapproval_id', $preapprovalId)->first();
    }

    public function findLatestByUser(int $userId, array $cols = ['*']): ?self
    {
        return PlanSubscription::query()
            ->select($cols)
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Outra assinatura vigente do usuário (diferente de `$exceptId`): indica
     * pagamento em duplicidade quando uma nova assinatura é ativada.
     */
    public function findOtherAuthorizedByUser(int $userId, int $exceptId, array $cols = ['*']): ?self
    {
        return PlanSubscription::query()
            ->select($cols)
            ->where('user_id', $userId)
            ->where('status', PlanSubscriptionStatusEnum::AUTHORIZED->value)
            ->where('id', '!=', $exceptId)
            ->orderByDesc('id')
            ->first();
    }

    public function findActiveAnnualByUser(int $userId, array $cols = ['*']): ?self
    {
        return PlanSubscription::query()
            ->select($cols)
            ->where('user_id', $userId)
            ->where('cycle', PlanCycleEnum::ANNUAL->value)
            ->where('status', PlanSubscriptionStatusEnum::AUTHORIZED->value)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Substitui os checkouts pendentes do usuário (mensais e anuais) por um
     * novo: o usuário pode desistir e escolher outra opção a qualquer momento.
     * O link antigo não é invalidado no Mercado Pago — o pagamento dele em
     * duplicidade é alertado pelo webhook.
     */
    public function cancelPendingByUser(int $userId, int $exceptId): int
    {
        return PlanSubscription::query()
            ->where('user_id', $userId)
            ->where('status', PlanSubscriptionStatusEnum::PENDING->value)
            ->where('id', '!=', $exceptId)
            ->update(['status' => PlanSubscriptionStatusEnum::CANCELLED->value]);
    }

    /**
     * Encerra as demais assinaturas anuais vigentes do usuário (substituídas
     * por uma renovação), mantendo uma única vigente por vez.
     */
    public function cancelOtherAuthorizedAnnual(int $userId, int $exceptId): int
    {
        return PlanSubscription::query()
            ->where('user_id', $userId)
            ->where('cycle', PlanCycleEnum::ANNUAL->value)
            ->where('status', PlanSubscriptionStatusEnum::AUTHORIZED->value)
            ->where('id', '!=', $exceptId)
            ->update(['status' => PlanSubscriptionStatusEnum::CANCELLED->value]);
    }

    /**
     * Assinaturas anuais vigentes cujo plano do usuário já venceu.
     *
     * @return Collection<int, self>
     */
    public function getExpiredAnnual(Carbon $now, array $cols = ['*']): Collection
    {
        return PlanSubscription::query()
            ->select($cols)
            ->with('user:id,current_plan,plan_expiration_date,plan_activated_on')
            ->where('cycle', PlanCycleEnum::ANNUAL->value)
            ->where('status', PlanSubscriptionStatusEnum::AUTHORIZED->value)
            ->whereHas('user', fn ($query) => $query->where('plan_expiration_date', '<=', $now))
            ->get();
    }

    /**
     * Assinaturas anuais vigentes que vencem até `$until` (inclusive as que
     * vencem hoje), com o usuário carregado para o envio do aviso.
     *
     * @return Collection<int, self>
     */
    public function getAnnualExpiringUntil(Carbon $until, array $cols = ['*']): Collection
    {
        return PlanSubscription::query()
            ->select($cols)
            ->with('user:id,name,email,plan_expiration_date')
            ->where('cycle', PlanCycleEnum::ANNUAL->value)
            ->where('status', PlanSubscriptionStatusEnum::AUTHORIZED->value)
            ->whereNull('cancel_at_period_end_at')
            ->whereHas('user', fn ($query) => $query->where('plan_expiration_date', '<=', $until))
            ->get();
    }

    /**
     * Assinatura vigente (paga) do usuário, de qualquer ciclo.
     */
    public function findAuthorizedByUser(int $userId, array $cols = ['*']): ?self
    {
        return PlanSubscription::query()
            ->select($cols)
            ->where('user_id', $userId)
            ->where('status', PlanSubscriptionStatusEnum::AUTHORIZED->value)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Assinatura com cancelamento de renovação agendado e plano ainda vigente.
     * O mensal já aparece como `cancelled` (webhook do Mercado Pago), por isso o
     * status não entra no filtro; o cancelamento só vale se for posterior à
     * última ativação, para uma assinatura nova não herdar o cancelamento antigo.
     */
    public function findScheduledCancelByUser(int $userId, array $cols = ['*']): ?self
    {
        return PlanSubscription::query()
            ->select($cols)
            ->where('user_id', $userId)
            ->whereNotNull('cancel_at_period_end_at')
            ->whereHas('user', fn ($query) => $query
                ->where('plan_expiration_date', '>', now())
                ->whereColumn('plan_subscriptions.cancel_at_period_end_at', '>=', 'users.plan_activated_on'))
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Assinaturas mensais com cancelamento agendado cujo plano do usuário já venceu.
     *
     * @return Collection<int, self>
     */
    public function getExpiredCancelledMonthly(Carbon $now, array $cols = ['*']): Collection
    {
        return PlanSubscription::query()
            ->select($cols)
            ->with('user:id,current_plan,plan_expiration_date,plan_activated_on')
            ->where('cycle', PlanCycleEnum::MONTHLY->value)
            ->whereNotNull('cancel_at_period_end_at')
            ->whereHas('user', fn ($query) => $query->where('plan_expiration_date', '<=', $now))
            ->get();
    }
}
