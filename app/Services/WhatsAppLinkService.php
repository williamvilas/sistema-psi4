<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\NumberHelper;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;

/**
 * Único ponto que monta links do WhatsApp. O número de suporte vem de config('info.whatsapp').
 */
class WhatsAppLinkService
{
    private const DEFAULT_MESSAGE = 'Oi, tudo bem? Gostaria de ajuda.';

    public function support(?string $message = null): string
    {
        return $this->build((string) config('info.whatsapp'), $message ?: self::DEFAULT_MESSAGE);
    }

    public function toNumber(string $phone, ?string $message = null): string
    {
        return $this->build($phone, $message);
    }

    // Parabéns sem menção a terapia/sessão: a notificação pode ser vista por terceiros no celular do paciente.
    public function birthdayMessage(string $patientName): string
    {
        return __('patients.whatsapp_birthday_message', ['name' => Str::title(Str::before(trim($patientName), ' '))]);
    }

    private function build(string $phone, ?string $message): string
    {
        // Agent novo por chamada: o user agent deve ser o da requisição atual (Octane mantém o processo vivo).
        $agent = new Agent;
        $agent->setUserAgent((string) request()->userAgent());

        $host = ($agent->isMobile() || $agent->isTablet()) ? 'api.whatsapp.com' : 'web.whatsapp.com';
        $url = 'https://'.$host.'/send?1=pt_BR&phone=55'.NumberHelper::onlyNumbers($phone);

        if (! empty($message)) {
            // Textos legados trazem quebras de linha como "%0A" literal.
            $message = str_replace('%0A', "\n", $message);
            $url .= '&text='.rawurlencode($message);
        }

        return $url;
    }
}
