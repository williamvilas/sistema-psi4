<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Jobs\RegisterLog;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class CacheHelper
{
    public static function get(string $key, mixed $default = null)
    {
        try {
            return Cache::get($key, $default);
        } catch (\Exception $e) {
            self::saveLogs($e);

            return null;
        }
    }

    public static function add(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null)
    {
        try {
            return Cache::add($key, $value, $ttl);
        } catch (\Exception $e) {
            self::saveLogs($e);

            return null;
        }
    }

    public static function set(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null)
    {
        try {
            return Cache::set($key, $value, $ttl);
        } catch (\Exception $e) {
            self::saveLogs($e);

            return null;
        }
    }

    public static function put(array|string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null)
    {
        try {
            return Cache::put($key, $value, $ttl);
        } catch (\Exception $e) {
            self::saveLogs($e);

            return null;
        }
    }

    /**
     * Retorna o valor em cache ou executa o callback e guarda o resultado.
     * Se o cache estiver indisponível, o callback roda direto (get/put já registram a falha).
     */
    public static function remember(string $key, \DateTimeInterface|\DateInterval|int|null $ttl, callable $callback): mixed
    {
        $cached = self::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();

        if ($value !== null) {
            self::put($key, $value, $ttl);
        }

        return $value;
    }

    public static function forget(string $key)
    {
        try {
            return Cache::forget($key);
        } catch (\Exception $e) {
            self::saveLogs($e);

            return null;
        }
    }

    public static function pull(string $key, mixed $default = null)
    {
        try {
            return Cache::pull($key, $default);
        } catch (\Exception $e) {
            self::saveLogs($e);

            return $default;
        }
    }

    /**
     * Executa o callback dentro de um lock distribuído. Se o cache estiver
     * indisponível, registra o erro e executa o callback sem lock, para não
     * quebrar o fluxo. O timeout de espera (LockTimeoutException) é relançado.
     */
    public static function lock(string $key, int $seconds, int $waitSeconds, callable $callback)
    {
        try {
            $lock = Cache::lock($key, $seconds);
            $lock->block($waitSeconds);
        } catch (LockTimeoutException $e) {
            throw $e;
        } catch (\Exception $e) {
            self::saveLogs($e);

            return $callback();
        }

        try {
            return $callback();
        } finally {
            try {
                $lock->release();
            } catch (\Exception $e) {
                self::saveLogs($e);
            }
        }
    }

    private static function saveLogs(\Exception $e): void
    {
        RegisterLog::dispatchSync([
            'data' => request()->all(),
            'message' => "CacheHelper->saveLogs: {$e->getMessage()}",
            'route' => request()->fullUrl(),
            'method' => request()->method(),
        ]);
    }
}
