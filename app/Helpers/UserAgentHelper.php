<?php

declare(strict_types=1);

namespace App\Helpers;

class UserAgentHelper
{
    private const BROWSERS = [
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'SamsungBrowser/' => 'Samsung Internet',
        'Firefox/' => 'Firefox',
        'Chrome/' => 'Chrome',
        'Safari/' => 'Safari',
    ];

    private const SYSTEMS = [
        'Android' => 'Android',
        'iPhone' => 'iPhone',
        'iPad' => 'iPad',
        'Windows' => 'Windows',
        'Mac OS X' => 'macOS',
        'CrOS' => 'ChromeOS',
        'Linux' => 'Linux',
    ];

    // Resumo legível do navegador/sistema (ex.: "Chrome no Windows") para a trilha de acesso.
    public static function describe(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'Dispositivo desconhecido';
        }

        $browser = self::firstMatch($userAgent, self::BROWSERS);
        $system = self::firstMatch($userAgent, self::SYSTEMS);

        return match (true) {
            $browser && $system => "{$browser} no {$system}",
            (bool) $browser => $browser,
            (bool) $system => $system,
            default => 'Dispositivo desconhecido',
        };
    }

    private static function firstMatch(string $userAgent, array $map): ?string
    {
        foreach ($map as $needle => $label) {
            if (str_contains($userAgent, $needle)) {
                return $label;
            }
        }

        return null;
    }
}
