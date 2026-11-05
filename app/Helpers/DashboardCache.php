<?php

declare(strict_types=1);

namespace App\Helpers;

class DashboardCache
{
    private const KEY = 'dashboard_report_v6_user_';

    private const MINUTES = 5;

    public static function get(int $userId): ?array
    {
        return CacheHelper::get(self::KEY.$userId);
    }

    public static function put(int $userId, array $report): void
    {
        CacheHelper::put(self::KEY.$userId, $report, now()->addMinutes(self::MINUTES));
    }

    public static function forget(int $userId): void
    {
        CacheHelper::forget(self::KEY.$userId);
    }
}
