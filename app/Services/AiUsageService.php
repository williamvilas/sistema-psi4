<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FeatureUsageEnum;
use App\Enums\PlanUserEnum;
use App\Models\User;
use App\Models\UserFeatureUsages;

/**
 * Cota mensal de tokens de IA (`config('plans.plans.*.total_ai_tokens')`). O microserviço de integrações
 * grava o consumo em `user_feature_usages` (feature `ai_tokens`, mês em UTC); virou o mês, não há linha
 * e o uso volta a zero sem nenhuma atualização. Com saldo, emite uma autorização de 30 min, assinada com
 * `AI_GRANT_SECRET`, que o microserviço confere antes de chamar a IA.
 */
readonly class AiUsageService
{
    public function __construct(
        private UserFeatureUsages $featureUsages = new UserFeatureUsages,
    ) {
    }

    /**
     * @return array{percent: float, reached: bool, token: ?string, message: ?string}
     */
    public function usage(User $user): array
    {
        $plan = $user->current_plan ?: PlanUserEnum::FREE->value;
        $limit = (int) config('plans.plans.'.$plan.'.total_ai_tokens', 0);
        $used = (int) ($this->featureUsages->countsByMonth($user->id, now()->year, now()->month)[FeatureUsageEnum::AI_TOKENS->value] ?? 0);
        $reached = $limit >= 0 && $used >= $limit;

        return [
            'percent' => self::percent($used, $limit),
            'reached' => $reached,
            'token' => $reached ? null : $this->grant($user),
            'message' => $reached ? __('plans.ai_limit_reached.'.($limit === 0 ? 'not_included' : $plan)) : null,
        ];
    }

    /** Ilimitado não mostra consumo; sem cota no plano, aparece como esgotado. */
    public static function percent(int $used, int $limit): float
    {
        if ($limit < 0) {
            return 0.0;
        }

        if ($limit === 0) {
            return 100.0;
        }

        return min(100.0, round($used / $limit * 100, 1));
    }

    /** Token `payload.assinatura` (base64url, HMAC-SHA256) com o id, o uuid e a validade em segundos Unix. */
    private function grant(User $user): string
    {
        $secret = (string) config('services.ai.grant_secret');

        if ($secret === '') {
            throw new \RuntimeException('AI_GRANT_SECRET não configurado.');
        }

        $payload = self::base64Url(json_encode([
            'uid' => $user->id,
            'uuid' => $user->uuid,
            'exp' => now()->addSeconds((int) config('services.ai.grant_ttl'))->getTimestamp(),
        ], JSON_THROW_ON_ERROR));

        return $payload.'.'.self::base64Url(hash_hmac('sha256', $payload, $secret, true));
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
