<?php

declare(strict_types=1);

namespace App\Wrappers;

use App\Jobs\RegisterLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Wrapper da API oficial do WhatsApp (Cloud API da Meta), enviando pelo número oficial da PSI4.
 */
class WhatsAppCloudWrapper
{
    private const BASE_URL = 'https://graph.facebook.com';

    private const CONNECT_TIMEOUT = 5;

    private const RETRYABLE_CLIENT_ERRORS = [408, 429];

    private ?Request $lastRequest = null;

    /**
     * Envia um modelo aprovado (obrigatório fora da janela de 24h de conversa).
     *
     * @param  string[]  $params  variáveis do corpo, na ordem do modelo ({{1}}, {{2}}...)
     * @param  string[]  $buttons  payload de cada botão de resposta rápida, na ordem do modelo
     * @return string id da mensagem (wamid)
     */
    public function sendTemplate(string $to, string $template, string $language, array $params, array $buttons = []): string
    {
        $components = [[
            'type' => 'body',
            'parameters' => array_map(fn (string $param) => ['type' => 'text', 'text' => $param], $params),
        ]];

        foreach (array_values($buttons) as $index => $payload) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'quick_reply',
                'index' => (string) $index,
                'parameters' => [['type' => 'payload', 'payload' => $payload]],
            ];
        }

        return $this->sendMessage([
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ]);
    }

    /**
     * Texto livre: só é entregue dentro da janela de 24h aberta pela última mensagem do contato.
     *
     * @return string id da mensagem (wamid)
     */
    public function sendText(string $to, string $body): string
    {
        return $this->sendMessage([
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $body],
        ]);
    }

    private function sendMessage(array $body): string
    {
        $response = $this->send(fn () => $this->client()
            ->post('/'.config('whatsapp.phone_number_id').'/messages', ['messaging_product' => 'whatsapp'] + $body));

        $this->assertSuccessful($response);

        $id = $response->json('messages.0.id');

        if (! is_string($id) || $id === '') {
            $this->registerFailure(
                'Resposta inesperada da API do WhatsApp ao enviar mensagem (id da mensagem ausente).',
                ['status' => $response->status(), 'response' => Str::limit($response->body(), 2000)],
            );

            throw new \UnexpectedValueException('invalid_response');
        }

        return $id;
    }

    private function client(): PendingRequest
    {
        $token = (string) config('whatsapp.access_token');

        if ($token === '' || (string) config('whatsapp.phone_number_id') === '') {
            throw new \DomainException(__('notifications.whatsapp_not_configured'));
        }

        return Http::baseUrl(self::BASE_URL.'/'.config('whatsapp.api_version'))
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout((int) config('whatsapp.timeout'))
            ->beforeSending(function (Request $request) {
                $this->lastRequest = $request;
            });
    }

    /**
     * Monta o cURL equivalente da requisição, com o header `Authorization` mascarado.
     */
    private function buildCurl(Request $request): string
    {
        $parts = ['curl -X '.$request->method().' '.escapeshellarg($request->url())];

        foreach ($request->headers() as $name => $values) {
            if (in_array(strtolower($name), ['content-length', 'host', 'user-agent'], true)) {
                continue;
            }

            $value = strtolower($name) === 'authorization' ? 'Bearer ***' : implode(', ', $values);
            $parts[] = '-H '.escapeshellarg($name.': '.$value);
        }

        if ($request->body() !== '') {
            $parts[] = '--data '.escapeshellarg($request->body());
        }

        return implode(' ', $parts);
    }

    private function send(\Closure $call): Response
    {
        $this->lastRequest = null;

        try {
            return $call();
        } catch (\Throwable $e) {
            report($e);

            $this->registerFailure($e->getMessage(), []);

            throw $e;
        }
    }

    private function registerFailure(string $message, array $data): void
    {
        $request = $this->lastRequest;

        if ($request === null) {
            return;
        }

        RegisterLog::dispatchSync([
            'message' => $message,
            'data' => $data + ['curl' => $this->buildCurl($request)],
            'route' => $request->url(),
            'method' => $request->method(),
        ]);
    }

    private function assertSuccessful(Response $response): void
    {
        if (! $response->failed()) {
            return;
        }

        $this->registerFailure(
            'Falha na requisição à API do WhatsApp (HTTP '.$response->status().')',
            ['status' => $response->status(), 'response' => Str::limit($response->body(), 2000)],
        );

        $exception = ($response->clientError() && ! in_array($response->status(), self::RETRYABLE_CLIENT_ERRORS, true))
            ? new \DomainException(__('notifications.whatsapp_rejected'))
            : $response->toException();

        throw $exception;
    }
}
