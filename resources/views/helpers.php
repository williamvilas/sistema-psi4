<?php

declare(strict_types=1);

use App\Enums\AgendaStatusEnum;
use App\Enums\DocumentsEnum;
use App\Enums\PlanUserEnum;
use App\Helpers\NumberHelper;
use App\Helpers\StringHelper;
use App\Services\WhatsAppLinkService;
use Carbon\Carbon;

if (! function_exists('isLinkMenuActive')) {
    function isLinkMenuActive($routeName, $submenu = false): string
    {
        $sub = ($submenu) ? 'submenu' : '';

        return request()->route()->getName() === $routeName ? 'active '.$sub : '';
    }
}

if (! function_exists('maskValue')) {
    /**
     * Aplica uma máscara (# = dígito) a um valor numérico puro. Ex.: maskValue('12345678', '#####-###').
     */
    function maskValue(?string $value, string $mask): string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if ($digits === '') {
            return '';
        }

        $masked = '';
        $position = 0;

        foreach (str_split($mask) as $char) {
            if ($position >= strlen($digits)) {
                break;
            }

            if ($char === '#') {
                $masked .= $digits[$position++];

                continue;
            }

            $masked .= $char;
        }

        return $masked;
    }
}

if (! function_exists('numberToWords')) {
    function numberToWords(int $number): string
    {
        return NumberHelper::numberToWords(number: $number);
    }
}

if (! function_exists('getIconPlanByUser')) {
    function getIconPlanByUser(string $currentPlan): string
    {
        $icons = [
            PlanUserEnum::PROFESSIONAL->value => '<i class="fas fa-star me-1 text-yellow"></i>',
        ];

        $icon = ! empty($icons[$currentPlan]) ? $icons[$currentPlan] : null;

        if ($icon) {
            return $icon;
        }

        return '';
    }
}

if (! function_exists('getColorPlanByUser')) {
    function getColorPlanByUser(string $currentPlan): string
    {
        $icons = [
            PlanUserEnum::PROFESSIONAL->value => 'border-p',
        ];

        $icon = ! empty($icons[$currentPlan]) ? $icons[$currentPlan] : null;

        if ($icon) {
            return $icon;
        }

        return '';
    }
}

if (! function_exists('getIconByDoc')) {
    function getIconByDoc(string $icon): string
    {
        $icons = [
            DocumentsEnum::PSYCHOLOGICAL_CERTIFICATE->value => 'fa-solid fa-file-circle-check',
            DocumentsEnum::ATTENDANCE_STATEMENT->value => 'fa-solid fa-file-signature',
            DocumentsEnum::PSYCHOLOGIST_STATEMENT->value => 'fa-solid fa-calendar-check',
            DocumentsEnum::PSYCHOLOGICAL_REPORT->value => 'fa-solid fa-file-lines',
        ];

        if (! empty($icons[$icon])) {
            return $icons[$icon];
        }

        return '';
    }
}

if (! function_exists('calculateProgressPercent')) {
    function calculateProgressPercent(float $currentValue, float $targetValue): float
    {
        if ($targetValue <= 0) {
            return 0;
        }

        $percent = ($currentValue / $targetValue) * 100;

        if ($percent < 0) {
            $percent = 0;
        }

        if ($percent > 100) {
            $percent = 100;
        }

        return round($percent, 2);
    }

}

if (! function_exists('displayText')) {

    function displayText(string $text)
    {
        $text = preg_replace(
            '/(https?:\/\/[^\s]+)/',
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
            e($text)
        );

        return nl2br($text);
    }
}

if (! function_exists('getTimeDifference')) {

    function getTimeDifference($date1, $date2)
    {
        $d1 = Carbon::parse($date1);
        $d2 = Carbon::parse($date2);

        $diffInSeconds = $d1->diffInSeconds($d2);

        if ($diffInSeconds < 60) {
            return $diffInSeconds.' seconds';
        }

        $diffInMinutes = $d1->diffInMinutes($d2);
        if ($diffInMinutes < 60) {
            return $diffInMinutes.' minutes';
        }

        $diffInHours = $d1->diffInHours($d2);
        if ($diffInHours < 24) {
            return $diffInHours.' hours';
        }

        $diffInDays = $d1->diffInDays($d2);

        return $diffInDays.' days';
    }
}

if (! function_exists('isOpenParentMenu')) {
    function isOpenParentMenu($prefixRoute): string
    {
        return request()->route()->getPrefix() === $prefixRoute ? 'show' : '';
    }
}

if (! function_exists('formatCRP')) {
    function formatCRP(?string $string = null, ?string $prefix = ''): ?string
    {
        if (! $string) {
            return null;
        }

        $numbers = preg_replace('/\D/', '', $string);
        if (strlen($numbers) < 3) {
            return null;
        }

        $region = substr($numbers, 0, 2);
        $numbers = substr($numbers, 2);

        return $prefix.sprintf('%s/%s', $region, $numbers);
    }
}

if (! function_exists('firstLetterCapitalized')) {
    function firstLetterCapitalized($string): string
    {
        return StringHelper::firstLetterCapitalized($string);
    }
}

if (! function_exists('formatCNPJ')) {
    function formatCNPJ($cnpj): string
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) !== 14) {
            return 'CNPJ inválido';
        }

        $formattedCNPJ = substr($cnpj, 0, 2).'.'.
            substr($cnpj, 2, 3).'.'.
            substr($cnpj, 5, 3).'/'.
            substr($cnpj, 8, 4).'-'.
            substr($cnpj, 12, 2);

        return $formattedCNPJ;
    }
}

if (! function_exists('formatNumber')) {
    function formatNumber($number): string
    {
        if ($number == '-1') {
            return 'Ilimitadas';
        }

        return number_format($number, 0, '', '.');
    }
}

if (! function_exists('formatDate')) {
    function formatDate(?string $date = null, $format = 'd/m/Y H:i:s', ?string $timezone = null): string
    {
        if (! $date) {
            return '-';
        }

        $carbon = Carbon::parse($date);

        if ($timezone) {
            $carbon->setTimezone($timezone);
        }

        return $carbon->format($format);
    }
}

if (! function_exists('formatDateByAgenda')) {
    function formatDateByAgenda(Carbon $date): string
    {
        if ($date->isToday()) {
            return 'Hoje';
        } elseif ($date->isTomorrow()) {
            return 'Amanhã';
        }

        return $date->format('d/m/Y');
    }
}

if (! function_exists('formatDateToday')) {
    function formatDateToday($date = null)
    {
        if (! $date) {
            return '-';
        }

        $data = DateTime::createFromFormat('Y-m-d H:i:s', $date);
        $agora = new DateTime;
        $diferenca = $agora->getTimestamp() - $data->getTimestamp();

        if ($data->format('Y-m-d') === date('Y-m-d')) {
            return 'Hoje às '.$data->format('H:i:s');
        } elseif ($diferenca < 172800) {
            return 'Ontem às '.$data->format('H:i:s');
        } else {
            return $data->format('d/m/Y H:i:s');
        }
    }
}

if (! function_exists('formatDateFriendly')) {
    function formatDateFriendly($date = null)
    {
        if (! $date) {
            return '-';
        }

        $data = DateTime::createFromFormat('Y-m-d H:i:s', $date);
        $agora = new DateTime;
        $diferenca = $agora->getTimestamp() - $data->getTimestamp();

        if ($diferenca < 60) {
            return 'Há '.$diferenca.' segundos atrás';
        } elseif ($diferenca < 3600) {
            $minutos = floor($diferenca / 60);

            return 'Há '.$minutos.' minutos atrás';
        } elseif ($diferenca < 86400) {
            $horas = floor($diferenca / 3600);

            return 'Há '.$horas.' horas atrás';
        } elseif ($diferenca < 172800) {
            return 'Ontem às '.$data->format('H:i:s');
        } else {
            return $data->format('d/m/Y H:i:s');
        }
    }
}

if (! function_exists('formatPhone')) {
    function formatPhone(string $phone): ?string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (strlen($phone) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $phone);
        } elseif (strlen($phone) === 11) {
            return preg_replace('/(\d{2})(\d{1})(\d{4})(\d{4})/', '($1) $2$3-$4', $phone);
        } else {
            return $phone;
        }
    }
}

if (! function_exists('formatCPF')) {
    function formatCPF(string $cpf)
    {
        // Remove tudo que não for número
        $cpf = preg_replace('/\D/', '', $cpf);

        // Verifica se tem 11 dígitos
        if (strlen($cpf) !== 11) {
            return null; // ou return $cpf;
        }

        // Aplica a máscara
        return substr($cpf, 0, 3).'.'.
            substr($cpf, 3, 3).'.'.
            substr($cpf, 6, 3).'-'.
            substr($cpf, 9, 2);
    }
}

if (! function_exists('converteInStrong')) {
    function converteInStrong($text)
    {
        return preg_replace('/\*(.*?)\*/', '<strong>$1</strong>', $text);
    }
}

if (! function_exists('formatCurrency')) {
    function formatCurrency($string, $prefix = 'R$ ')
    {
        $string = (float) $string;

        return $prefix.number_format($string, 2, ',', '.');
    }
}

if (! function_exists('formatStatusAgenda')) {
    function formatStatusAgenda(AgendaStatusEnum $status)
    {
        return __('agenda_status.'.$status->value);
    }
}

if (! function_exists('generateLinkWhatsApp')) {
    /**
     * URL pública que redireciona ao WhatsApp de suporte (web ou mobile, resolvido na rota).
     */
    function generateLinkWhatsApp(?string $message = null): string
    {
        return route('whatsapp.contact', array_filter(['message' => $message]));
    }
}

if (! function_exists('generateLinkWhatsAppByNumber')) {
    function generateLinkWhatsAppByNumber(string $phone, string $message = ''): string
    {
        return app(WhatsAppLinkService::class)->toNumber($phone, $message);
    }
}

if (! function_exists('truncate')) {

    function truncate($val, $f = '0'): float
    {
        $val = (string) $val;
        if (($p = strpos($val, '.')) !== false) {
            $val = floatval(substr($val, 0, $p + 1 + $f));
        }

        return (float) $val;
    }
}

if (! function_exists('calculateMiniIndexCosts')) {
    function calculateMiniIndexCosts(
        int $contracts,
        int $points,
        float $brokerage = 0.0,
        float $feeEmoluments = 0.06,   // R$ 0.06 per contract (approx.)
        float $feeSettlement = 0.24    // R$ 0.24 per contract
    ): array {

        // Total B3 fee per contract (emoluments + settlement)
        $totalB3FeePerContract = $feeEmoluments + $feeSettlement;

        // Total B3 fees (round trip = buy + sell)
        $b3Fees = $totalB3FeePerContract * $contracts * 2;

        // Include brokerage (both sides)
        $totalCost = $b3Fees + ($brokerage * 2);

        // Gross profit/loss calculation (each point = R$ 0.20 per contract)
        $grossProfit = $points * 0.20 * $contracts;

        // Net profit (after all fees)
        $netProfit = $grossProfit - $totalCost;

        return [
            'contracts' => $contracts,
            'points' => $points,
            'gross_profit' => round($grossProfit, 2),
            'b3_fees' => round($b3Fees, 2),
            'total_brokerage' => round($brokerage * 2, 2),
            'total_cost' => round($totalCost, 2),
            'net_profit' => round($netProfit, 2),
        ];
    }
}

if (! function_exists('calculateIncomeTax')) {
    function calculateIncomeTax(float $netProfit, float $taxRate): float
    {
        // If there is no profit or it’s negative, no tax is due
        if ($netProfit <= 0) {
            return 0.0;
        }

        // Convert percentage to decimal and calculate
        $taxAmount = $netProfit * ($taxRate / 100);

        return round($taxAmount, 2);
    }
}

if (! function_exists('weekCurrent')) {

    function weekCurrent(?string $date = null): string
    {
        $date = $date ? new DateTime($date) : new DateTime;

        $dayOfWeek = (int) $date->format('w'); // 0 (dom) a 6 (sáb)

        $start = clone $date;
        $end = clone $date;

        $start->modify("-{$dayOfWeek} days");
        $end->modify('+'.(6 - $dayOfWeek).' days');

        return $start->format('d M').' até '.$end->format('d M');
    }
}

if (! function_exists('calculateAge')) {
    function calculateAge(string $birthDate, string $timezone = 'America/Sao_Paulo'): int
    {
        return Carbon::parse($birthDate, $timezone)->age;
    }
}

if (! function_exists('getCDN')) {
    function getCDN(string $path, int $minutes = 5): string
    {
        $userID = \Illuminate\Support\Facades\Auth::id();
        $key = $path.'_by_user_'.$userID;

        $exists = \App\Helpers\CacheHelper::get(key: $key);

        if ($exists) {
            return $exists;
        }

        $expirationAt = now()->addMinutes($minutes);

        $url = \Illuminate\Support\Facades\Storage::temporaryUrl(path: $path, expiration: $expirationAt);
        \App\Helpers\CacheHelper::put(key: $key, value: $url, ttl: $expirationAt);

        return $url;
    }
}
