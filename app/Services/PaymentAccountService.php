<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentAccountsStatusEnum;
use App\Enums\PaymentGatewayEnum;
use App\Helpers\CacheHelper;
use App\Models\PaymentAccount;
use App\Wrappers\MercadoPagoConnectWrapper;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

readonly class PaymentAccountService
{
    private const STATE_TTL_MINUTES = 10;

    // Margem para não usar um token que expira durante a requisição
    private const TOKEN_EXPIRY_MARGIN_SECONDS = 300;

    public function __construct(
        private PaymentAccount $paymentAccount,
        private MercadoPagoConnectWrapper $connectWrapper,
    ) {
    }

    public function details(int $userId): ?PaymentAccount
    {
        return $this->paymentAccount->findByUserAndProvider($userId, PaymentGatewayEnum::MERCADO_PAGO->value);
    }

    /**
     * Gera um `state` OAuth único, vinculado ao usuário autenticado, e o
     * persiste no cache compartilhado (Redis) com expiração curta — evita
     * CSRF no callback e sobrevive a restart/múltiplas instâncias.
     */
    public function buildAuthorizationUrl(int $userId): string
    {
        $state = Str::random(40);

        CacheHelper::put($this->stateCacheKey($state), $userId, now()->addMinutes(self::STATE_TTL_MINUTES));

        return $this->connectWrapper->buildAuthorizationUrl($state);
    }

    /**
     * Troca o `code` do callback OAuth pelos tokens da conta do terapeuta e
     * persiste/atualiza o `PaymentAccount` conectado.
     */
    public function connect(string $code, string $state, int $userId): void
    {
        $cachedUserId = CacheHelper::pull($this->stateCacheKey($state));

        if ($cachedUserId === null || (int) $cachedUserId !== $userId) {
            throw new \DomainException(__('payments.oauth_state_invalid'));
        }

        $tokens = $this->connectWrapper->exchangeAuthorizationCode($code);

        DB::transaction(function () use ($tokens, $userId) {
            $account = $this->paymentAccount->newQuery()->firstOrNew([
                'user_id' => $userId,
                'provider' => PaymentGatewayEnum::MERCADO_PAGO->value,
            ]);

            $account->fill([
                'public_key' => $tokens['public_key'],
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'mp_user_id' => $tokens['user_id'],
                'scope' => $tokens['scope'],
                'token_expires_at' => now()->addSeconds($tokens['expires_in']),
                'status' => PaymentAccountsStatusEnum::INTEGRATED->value,
            ]);

            $account->save();
        });
    }

    public function disconnect(int $userId): void
    {
        $account = $this->details($userId);

        if (! $account) {
            return;
        }

        $account->update([
            'access_token' => null,
            'refresh_token' => null,
            'public_key' => null,
            'mp_user_id' => null,
            'scope' => null,
            'token_expires_at' => null,
            'status' => PaymentAccountsStatusEnum::PENDING->value,
        ]);
    }

    /**
     * Retorna um access token válido para a conta conectada, renovando-o via
     * refresh token quando necessário. Marca a conta para reautorização em
     * vez de usar silenciosamente o token da própria PSI4 quando a renovação
     * é recusada pelo Mercado Pago.
     *
     * A renovação é serializada por conta (lock): o Mercado Pago rotaciona o
     * refresh token, então duas renovações simultâneas com o mesmo token fariam
     * a segunda ser recusada e a conta cair indevidamente em reautorização.
     * Falhas transitórias (rede, 5xx, 429) são relançadas para o job tentar
     * novamente, sem invalidar a conexão do terapeuta.
     */
    public function getValidAccessToken(PaymentAccount $account): string
    {
        if ($this->hasUsableToken($account)) {
            return $account->access_token;
        }

        try {
            return CacheHelper::lock('mp_token_refresh:'.$account->id, 30, 10, function () use ($account) {
                // Outro processo pode ter renovado enquanto aguardávamos o lock
                $account->refresh();

                if ($this->hasUsableToken($account)) {
                    return $account->access_token;
                }

                return $this->refreshToken($account);
            });
        } catch (LockTimeoutException) {
            throw new \RuntimeException('token_refresh_lock_timeout');
        }
    }

    private function hasUsableToken(PaymentAccount $account): bool
    {
        return $account->access_token
            && $account->token_expires_at?->gt(now()->addSeconds(self::TOKEN_EXPIRY_MARGIN_SECONDS));
    }

    private function refreshToken(PaymentAccount $account): string
    {
        if (! $account->refresh_token) {
            $this->markNeedsReauth($account);

            throw new \DomainException(__('payments.account_needs_reauth'));
        }

        try {
            $tokens = $this->connectWrapper->refreshAccessToken($account->refresh_token);
        } catch (\DomainException) {
            // Recusa definitiva (4xx) do Mercado Pago: só o terapeuta resolve
            $this->markNeedsReauth($account);

            throw new \DomainException(__('payments.account_needs_reauth'));
        }

        $account->update([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => now()->addSeconds($tokens['expires_in']),
            'status' => PaymentAccountsStatusEnum::INTEGRATED->value,
        ]);

        return $tokens['access_token'];
    }

    private function markNeedsReauth(PaymentAccount $account): void
    {
        $account->update(['status' => PaymentAccountsStatusEnum::NEEDS_REAUTH->value]);
    }

    private function stateCacheKey(string $state): string
    {
        return 'mp_oauth_state:'.$state;
    }
}
