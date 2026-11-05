<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GoogleCalendarStatusEnum;
use App\Exceptions\GoogleCalendarScopeMissingException;
use App\Helpers\CacheHelper;
use App\Jobs\RegisterLog;
use App\Models\GoogleCalendarAccount;
use App\Wrappers\GoogleCalendarWrapper;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoogleCalendarService
{
    private const STATE_TTL_MINUTES = 10;

    // Margem para não usar um token que expira durante a requisição
    private const TOKEN_EXPIRY_MARGIN_SECONDS = 300;

    public function __construct(
        private readonly GoogleCalendarAccount $account,
        private readonly GoogleCalendarWrapper $wrapper,
    ) {
    }

    public function details(int $userId): ?GoogleCalendarAccount
    {
        return $this->account->findByUser($userId);
    }

    public function isConnected(int $userId): bool
    {
        return $this->account->isIntegratedByUser($userId);
    }

    /**
     * Gera um `state` OAuth único, vinculado ao usuário autenticado, no cache
     * compartilhado e com expiração curta (evita CSRF no callback).
     */
    public function buildAuthorizationUrl(int $userId, ?string $origin = null): string
    {
        $state = Str::random(40);

        CacheHelper::put($this->stateCacheKey($state), $userId, now()->addMinutes(self::STATE_TTL_MINUTES));

        if ($origin !== null) {
            CacheHelper::put($this->originCacheKey($state), $origin, now()->addMinutes(self::STATE_TTL_MINUTES));
        }

        return $this->wrapper->buildAuthorizationUrl($state);
    }

    /**
     * Troca o `code` do callback pelos tokens da conta Google do psicólogo e
     * persiste/atualiza a conexão.
     */
    public function connect(string $code, string $state, int $userId): void
    {
        $cachedUserId = CacheHelper::pull($this->stateCacheKey($state));

        if ($cachedUserId === null || (int) $cachedUserId !== $userId) {
            throw new \DomainException(__('google-calendar.oauth_state_invalid'));
        }

        $tokens = $this->wrapper->exchangeAuthorizationCode($code);

        // O psicólogo pode desmarcar permissões na tela de consentimento: todas são obrigatórias
        $granted = explode(' ', (string) $tokens['scope']);

        if (array_diff(config('google-calendar.required_scopes'), $granted) !== []) {
            if ($tokens['refresh_token']) {
                $this->wrapper->revokeToken($tokens['refresh_token']);
            }

            throw new GoogleCalendarScopeMissingException(__('google-calendar.scope_missing'));
        }

        if (! $tokens['refresh_token']) {
            throw new \DomainException(__('google-calendar.oauth_failed'));
        }

        DB::transaction(function () use ($tokens, $userId) {
            $account = $this->account->newQuery()->firstOrNew(['user_id' => $userId]);

            // Outra conta Google: o calendário escolhido antes não vale mais
            if ($account->exists && $account->google_user_id !== $tokens['google_user_id']) {
                $account->calendar_id = 'primary';
            }

            // Conta nova: convidar o paciente e bloquear horários ocupados vêm ativados
            if (! $account->exists) {
                $account->invite_patient = true;
                $account->block_busy = true;
            }

            $account->fill([
                'google_user_id' => $tokens['google_user_id'],
                'email' => $tokens['email'],
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'scope' => $tokens['scope'],
                'token_expires_at' => now()->addSeconds($tokens['expires_in']),
                'status' => GoogleCalendarStatusEnum::INTEGRATED->value,
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

        if ($account->refresh_token) {
            $this->wrapper->revokeToken($account->refresh_token);
        }

        $account->update([
            'access_token' => null,
            'refresh_token' => null,
            'scope' => null,
            'token_expires_at' => null,
            'status' => GoogleCalendarStatusEnum::PENDING->value,
        ]);
    }

    /**
     * @return array<int, array{id: string, summary: string, primary: bool}>
     */
    public function calendars(int $userId): array
    {
        $account = $this->requireIntegratedAccount($userId);

        return $this->wrapper->listWritableCalendars($this->getValidAccessToken($account));
    }

    public function updatePreferences(int $userId, string $calendarId, bool $invitePatient, bool $blockBusy = false): void
    {
        $account = $this->requireIntegratedAccount($userId);

        if ($blockBusy && ! $account->canReadFreeBusy()) {
            throw new \DomainException(__('google-calendar.freebusy_scope_missing'));
        }

        $calendarIds = array_column($this->wrapper->listWritableCalendars($this->getValidAccessToken($account)), 'id');

        if (! in_array($calendarId, $calendarIds, true)) {
            throw new \DomainException(__('google-calendar.calendar_not_found'));
        }

        $account->update(['calendar_id' => $calendarId, 'invite_patient' => $invitePatient, 'block_busy' => $blockBusy]);
    }

    /**
     * Intervalos em que o psicólogo está ocupado no Google (calendário principal
     * e o das consultas), para bloquear horários na agenda. Sem a opção ligada
     * devolve vazio. Falha do Google nunca derruba a agenda: sem a informação,
     * nenhum horário é bloqueado (falhas HTTP já ficam registradas pelo wrapper).
     *
     * Com `$fresh` ignora o cache (usado na hora de confirmar um agendamento).
     *
     * @return array<int, array{start: Carbon, end: Carbon}>
     */
    public function busyIntervals(int $userId, Carbon $startUtc, Carbon $endUtc, bool $fresh = false): array
    {
        $account = $this->account->findBusyBlockingByUser($userId);

        if (! $account || ! $account->canReadFreeBusy()) {
            return [];
        }

        $key = sprintf('gc_busy:%d:%d:%d:%d:%s', $userId, $this->busyCacheVersion($userId), $startUtc->timestamp, $endUtc->timestamp, md5($account->calendar_id));
        $intervals = $fresh ? null : CacheHelper::get($key);

        if (! is_array($intervals)) {
            try {
                $intervals = $this->wrapper->queryBusyIntervals(
                    accessToken: $this->getValidAccessToken($account),
                    calendarIds: array_values(array_unique(['primary', $account->calendar_id])),
                    startUtc: $startUtc,
                    endUtc: $endUtc,
                );
            } catch (\Throwable $e) {
                RegisterLog::dispatchSync([
                    'message' => 'Falha ao consultar horários ocupados no Google: '.$e->getMessage(),
                    'route' => 'google-calendar/busyIntervals',
                    'method' => 'POST',
                ]);

                return [];
            }

            CacheHelper::put($key, $intervals, now()->addSeconds((int) config('google-calendar.busy_cache_seconds')));
        }

        return array_map(fn (array $i) => ['start' => Carbon::parse($i['start'])->utc(), 'end' => Carbon::parse($i['end'])->utc()], $intervals);
    }

    /**
     * Descarta as consultas em cache do psicólogo: a próxima listagem de horários
     * volta a perguntar ao Google (ex.: um horário foi recusado por estar ocupado).
     */
    public function flushBusyCache(int $userId): void
    {
        CacheHelper::set('gc_busy_version:'.$userId, $this->busyCacheVersion($userId) + 1, now()->addDay());
    }

    private function busyCacheVersion(int $userId): int
    {
        return (int) CacheHelper::get('gc_busy_version:'.$userId, 0);
    }

    /**
     * Retorna um access token válido, renovando-o via refresh token quando
     * necessário. A renovação é serializada por conta (lock) e, se o Google a
     * recusar (acesso revogado), a conta vai para reautorização. Falhas
     * transitórias (rede, 5xx, 429) são relançadas para o job tentar de novo.
     */
    public function getValidAccessToken(GoogleCalendarAccount $account): string
    {
        if ($this->hasUsableToken($account)) {
            return $account->access_token;
        }

        try {
            return CacheHelper::lock('gc_token_refresh:'.$account->id, 30, 10, function () use ($account) {
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

    private function requireIntegratedAccount(int $userId): GoogleCalendarAccount
    {
        $account = $this->account->findIntegratedByUser($userId);

        if (! $account) {
            throw new \DomainException(__('google-calendar.not_connected'));
        }

        return $account;
    }

    private function hasUsableToken(GoogleCalendarAccount $account): bool
    {
        return $account->access_token
            && $account->token_expires_at?->gt(now()->addSeconds(self::TOKEN_EXPIRY_MARGIN_SECONDS));
    }

    private function refreshToken(GoogleCalendarAccount $account): string
    {
        if (! $account->refresh_token) {
            $this->markNeedsReauth($account);

            throw new \DomainException(__('google-calendar.account_needs_reauth'));
        }

        try {
            $tokens = $this->wrapper->refreshAccessToken($account->refresh_token);
        } catch (\DomainException) {
            // Recusa definitiva (4xx, ex.: acesso revogado): só o psicólogo resolve
            $this->markNeedsReauth($account);

            throw new \DomainException(__('google-calendar.account_needs_reauth'));
        }

        $account->update([
            'access_token' => $tokens['access_token'],
            'token_expires_at' => now()->addSeconds($tokens['expires_in']),
            'status' => GoogleCalendarStatusEnum::INTEGRATED->value,
        ]);

        return $tokens['access_token'];
    }

    private function markNeedsReauth(GoogleCalendarAccount $account): void
    {
        $account->update(['status' => GoogleCalendarStatusEnum::NEEDS_REAUTH->value]);
    }

    /**
     * Tela de onde partiu a conexão (ex.: `agenda`), para voltar a ela após o callback.
     */
    public function pullOrigin(string $state): ?string
    {
        $origin = CacheHelper::pull($this->originCacheKey($state));

        return is_string($origin) ? $origin : null;
    }

    private function originCacheKey(string $state): string
    {
        return 'gc_oauth_origin:'.$state;
    }

    private function stateCacheKey(string $state): string
    {
        return 'gc_oauth_state:'.$state;
    }
}
