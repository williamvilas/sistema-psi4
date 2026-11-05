<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FeatureUsageEnum;
use App\Enums\PlanUserEnum;
use App\Exceptions\PlanLimitException;
use App\Models\User;
use App\Models\UserFeatureUsages;
use Illuminate\Support\Facades\DB;

/**
 * Limites mensais do plano (`config('plans.plans.*.total_*')`). O uso fica em
 * `user_feature_usages`, por mês de criação: cancelar ou apagar não devolve o saldo.
 */
readonly class PlanLimitService
{
    /** Limite do plano => features que somam nele (agendamentos da psicóloga e do link público dividem o mesmo saldo). */
    public const LIMITS = [
        'total_agenda' => [FeatureUsageEnum::AGENDA, FeatureUsageEnum::PUBLIC_BOOKING],
        'total_medical_records' => [FeatureUsageEnum::MEDICAL_RECORDS],
        'total_documents_issued' => [FeatureUsageEnum::DOCUMENTS],
        'total_scales' => [FeatureUsageEnum::SCALE_APPLICATIONS, FeatureUsageEnum::SCALE_LINKS],
        'total_payment_orders' => [FeatureUsageEnum::CREATE_PAYMENT_ORDER],
    ];

    public function __construct(
        private UserFeatureUsages $featureUsages = new UserFeatureUsages,
        private User $users = new User,
    ) {
    }

    /**
     * Registra o uso da feature, barrando quando passa do limite do plano. Chamado
     * dentro da transação da criação: se ela falhar, o uso é revertido junto.
     * As linhas do mês ficam travadas até o commit (requisições simultâneas não passam do limite).
     *
     * @throws PlanLimitException
     */
    public function consume(int $userId, FeatureUsageEnum $feature, int $amount = 1): void
    {
        $limitKey = self::limitKeyOf($feature);
        $limit = $limitKey ? $this->limitOf($userId, $limitKey) : -1;

        if ($limit < 0) {
            $this->featureUsages->upsert($userId, $feature, $amount);

            return;
        }

        DB::transaction(function () use ($userId, $feature, $amount, $limitKey, $limit) {
            $now = now();
            $features = self::LIMITS[$limitKey];

            $this->featureUsages->createMissingForMonth($userId, $features, $now->year, $now->month);
            $used = $this->featureUsages->sumForUpdate($userId, $features, $now->year, $now->month);

            if ($used + $amount > $limit) {
                throw new PlanLimitException(__('plans.limit_reached.'.$limitKey));
            }

            $this->featureUsages->upsert($userId, $feature, $amount);
        });
    }

    /** Verificação prévia (sem registrar o uso), para avisar antes de a psicóloga começar. */
    public function hasReached(int $userId, string $limitKey): bool
    {
        $limit = $this->limitOf($userId, $limitKey);

        if ($limit < 0) {
            return false;
        }

        $counts = $this->featureUsages->countsByMonth($userId, now()->year, now()->month);

        return $this->usedOf($counts, $limitKey) >= $limit;
    }

    /**
     * Uso do mês de cada limite do plano da usuária (só os limitados), para o aviso no painel.
     *
     * @return array<string, array{used: int, limit: int}>
     */
    public function usage(User $user): array
    {
        $plan = $user->current_plan ?: PlanUserEnum::FREE->value;
        $limited = array_filter(self::LIMITS, fn (string $key) => $this->configuredLimit($plan, $key) >= 0, ARRAY_FILTER_USE_KEY);

        if (! $limited) {
            return [];
        }

        $counts = $this->featureUsages->countsByMonth($user->id, now()->year, now()->month);
        $usage = [];

        foreach (array_keys($limited) as $key) {
            $usage[$key] = ['used' => $this->usedOf($counts, $key), 'limit' => $this->configuredLimit($plan, $key)];
        }

        return $usage;
    }

    public static function limitKeyOf(FeatureUsageEnum $feature): ?string
    {
        foreach (self::LIMITS as $key => $features) {
            if (in_array($feature, $features, true)) {
                return $key;
            }
        }

        return null;
    }

    private function limitOf(int $userId, string $limitKey): int
    {
        $plan = $this->users->findCurrentPlanById($userId) ?: PlanUserEnum::FREE->value;

        return $this->configuredLimit($plan, $limitKey);
    }

    /** Limite ausente no config vale como não incluso (0), como na tela de planos. */
    private function configuredLimit(string $plan, string $limitKey): int
    {
        return (int) config('plans.plans.'.$plan.'.'.$limitKey, 0);
    }

    /** @param  array<string, int>  $counts */
    private function usedOf(array $counts, string $limitKey): int
    {
        return array_sum(array_map(fn (FeatureUsageEnum $feature) => $counts[$feature->value] ?? 0, self::LIMITS[$limitKey]));
    }
}
