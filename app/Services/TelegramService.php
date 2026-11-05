<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

readonly class TelegramService
{
    private string $uri;

    public function __construct()
    {
        $this->uri = config('telegram.api').'/bot'.config('telegram.token');
    }

    public function sendMessage(string $message): void
    {
        try {
            $m = 'Projeto: '.config('app.name')." \n";
            $m .= 'Horário: '.now()->setTimezone('America/Sao_Paulo')->format('d/m/y H:i')." \n";
            $m .= "{$message}\n";
            $message = urlencode($m);
            $url = $this->uri.'/sendMessage?chat_id='.config('telegram.chat_id')."&text={$message}";

            $result = Http::get($url);

            if ($result->getStatusCode() != 200) {
                throw new \Exception('Error send message Telegram.');
            }
        } catch (\Throwable $e) {
            throw $e;
        }
    }
}
