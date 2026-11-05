<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Enums\PaginateCacheContextEnum;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cache das listagens paginadas por contexto + usuário + filtros + página.
 *
 * A invalidação troca o "token de versão" do contexto/usuário: as chaves antigas deixam de ser
 * lidas (e expiram pelo TTL), sem precisar varrer o Redis. O token é lido antes da query, então
 * uma escrita concorrente manda o resultado velho para uma chave que ninguém mais lê.
 */
class PaginateCache
{
    private const PREFIX = 'paginate';

    // Versão global (templates sem dono, user_id null), compartilhada por todos os usuários
    private const GLOBAL_USER_ID = 0;

    /**
     * @param  array  $params  filtros + limit + scope; a página atual é incluída automaticamente
     * @param  bool  $withGlobal  a listagem mistura dados globais (user_id null) além dos do usuário
     */
    public static function remember(
        PaginateCacheContextEnum $context,
        int $userId,
        array $params,
        callable $callback,
        bool $withGlobal = false,
    ): mixed {
        $versions = self::version($context, $userId);

        if ($withGlobal) {
            $versions .= '.'.self::version($context, self::GLOBAL_USER_ID);
        }

        $params['page'] = Paginator::resolveCurrentPage();
        $key = sprintf('%s:%s:u%d:v%s:%s', self::PREFIX, $context->value, $userId, $versions, self::hash($params));

        return CacheHelper::remember($key, now()->addMinutes($context->ttlMinutes()), $callback);
    }

    /**
     * Invalida as listagens do usuário. Dentro de transação, espera o commit para que uma leitura
     * concorrente não recacheie o dado antigo.
     */
    public static function forget(int $userId, PaginateCacheContextEnum ...$contexts): void
    {
        DB::afterCommit(function () use ($userId, $contexts) {
            foreach ($contexts as $context) {
                self::bump($context, $userId);
            }
        });
    }

    public static function forgetGlobal(PaginateCacheContextEnum ...$contexts): void
    {
        self::forget(self::GLOBAL_USER_ID, ...$contexts);
    }

    public static function forgetPatientDependents(int $userId): void
    {
        self::forget($userId, ...PaginateCacheContextEnum::patientDependents());
    }

    private static function versionKey(PaginateCacheContextEnum $context, int $userId): string
    {
        return sprintf('%s_v:%s:u%d', self::PREFIX, $context->value, $userId);
    }

    private static function version(PaginateCacheContextEnum $context, int $userId): string
    {
        $key = self::versionKey($context, $userId);
        $version = CacheHelper::get($key);

        if ($version === null) {
            // add é atômico: em corrida, todos passam a usar o token de quem gravou primeiro
            CacheHelper::add($key, Str::random(10));
            $version = CacheHelper::get($key) ?? '0';
        }

        return (string) $version;
    }

    private static function bump(PaginateCacheContextEnum $context, int $userId): void
    {
        CacheHelper::put(self::versionKey($context, $userId), Str::random(10));
    }

    private static function hash(array $params): string
    {
        return md5((string) json_encode(self::normalize($params)));
    }

    private static function normalize(array $params): array
    {
        $normalized = [];

        foreach ($params as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d\TH:i:s.uP');
            } elseif ($value instanceof BackedEnum) {
                $value = $value->value;
            } elseif (is_array($value)) {
                $value = self::normalize($value);
            }

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $normalized[$key] = $value;
        }

        ksort($normalized);

        return $normalized;
    }
}
