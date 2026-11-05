<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Jobs\RegisterLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class UserHelper
{
    public static function getFirstName(?User $user = null): string
    {
        if (! $user) {
            $user = Auth::user();
        }

        $names = explode(' ', $user->name);

        return $names[0];
    }

    public static function getDateCurrent(): Carbon
    {
        return now()->setTimezone(self::getTimezone());
    }

    public static function getTimezone(): string
    {
        return Auth::user()->timezone;
    }

    public static function getPhoto(?User $user = null): string
    {
        if (! $user) {
            $user = Auth::user();
        }

        $key = 'photo_user_'.$user->id;
        $exists = CacheHelper::get($key);

        if ($exists) {
            return $exists;
        }

        $photo = $user->photo;

        if ($photo) {
            $link = FileHelper::getLinkTemp(path: $photo, minutes: 60);
            CacheHelper::put($key, $link, now()->addMinutes(59));

            return $link;
        }

        return asset(config('app.avatar'));
    }

    public static function removeCaches(?User $user = null): void
    {
        if (! $user) {
            $user = Auth::user();
        }

        CacheHelper::forget('photo_user_'.$user->id);
    }

    /**
     * Aceita apenas caminho interno (GET) que exista como rota; qualquer outra coisa é descartada (open redirect).
     */
    public static function sanitizeRedirect(?string $url): ?string
    {
        if (! $url || ! str_starts_with($url, '/') || str_starts_with($url, '//') || str_contains($url, '\\') || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return null;
        }

        $path = '/'.trim((string) parse_url($url, PHP_URL_PATH), '/');

        if (in_array($path, ['/login', '/logout', '/register'], true)) {
            return null;
        }

        try {
            Route::getRoutes()->match(Request::create($url, 'GET'));
        } catch (\Throwable) {
            return null;
        }

        return $url;
    }

    public static function resolveLoginRedirect(): string
    {
        $redirect = session('redirect');
        self::updateLastLogin();

        if ($redirect) {
            session()->forget('redirect');

            return $redirect;
        }

        return route('dashboard', absolute: false);
    }

    public static function updateLastLogin(): void
    {
        try {
            $user = Auth::user();
            $user->last_login = now()->format('Y-m-d H:i:s');
            $user->save();
        } catch (\Exception $e) {
            RegisterLog::dispatchSync([
                'data' => [],
                'message' => "UserHelper->updateLastLogin: {$e->getMessage()}",
                'route' => request()->fullUrl(),
                'method' => request()->method(),
            ]);
        }
    }

    public static function generateCode(int $length = 10): string
    {
        do {
            $code = strtoupper(Str::random($length));
        } while (User::where('code', $code)->exists());

        return $code;
    }

    public static function getCurrentPlan(?User $user = null): string
    {
        $user ??= auth()->user();

        return $user->current_plan;
    }
}
