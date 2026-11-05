<?php

declare(strict_types=1);

namespace App\Wrappers;

use App\Jobs\RegisterLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Wrapper para o fluxo Mercado Pago Connect (OAuth de marketplace/split
 * payments): troca/renovação do token de acesso do terapeuta conectado e
 * criação/atualização de cobranças (`/checkout/preferences`) e estornos usando o token dele.
 */
class MercadoPagoConnectWrapper
{
    private const BASE_URL = 'https://api.mercadopago.com';

    private const CONNECT_TIMEOUT = 5;

    private const RETRYABLE_CLIENT_ERRORS = [408, 429];

    private ?Request $lastRequest = null;

    /**
     * Monta a URL de autorização OAuth para o terapeuta conectar sua conta
     * Mercado Pago à PSI4.
     */
    public function buildAuthorizationUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => (string) config('mercadopago.oauth.client_id'),
            'response_type' => 'code',
            'platform_id' => 'mp',
            'redirect_uri' => (string) config('mercadopago.oauth.redirect_uri'),
            'state' => $state,
        ]);

        return 'https://auth.mercadopago.com/authorization?'.$query;
    }

    /**
     * Troca o `code` retornado no callback OAuth pelos tokens da conta do
     * terapeuta conectado.
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, user_id: int, public_key: ?string, scope: ?string}
     */
    public function exchangeAuthorizationCode(string $code): array
    {
        $response = $this->send(fn () => $this->baseClient()
            ->asForm()
            ->post('/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => (string) config('mercadopago.oauth.client_id'),
                'client_secret' => (string) config('mercadopago.oauth.client_secret'),
                'code' => $code,
                'redirect_uri' => (string) config('mercadopago.oauth.redirect_uri'),
            ]));

        $this->assertSuccessful($response, 'payments.oauth_failed');

        return $this->parseTokenResponse($response);
    }

    /**
     * Renova o token de acesso do terapeuta conectado. O Mercado Pago pode
     * rotacionar o refresh token: ambos os valores retornados devem substituir
     * atomicamente os anteriores.
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, user_id: int, public_key: ?string, scope: ?string}
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $response = $this->send(fn () => $this->baseClient()
            ->asForm()
            ->post('/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => (string) config('mercadopago.oauth.client_id'),
                'client_secret' => (string) config('mercadopago.oauth.client_secret'),
                'refresh_token' => $refreshToken,
            ]));

        $this->assertSuccessful($response, 'payments.oauth_failed');

        return $this->parseTokenResponse($response);
    }

    /**
     * Cria a preference (`/checkout/preferences`) da cobrança na conta do
     * terapeuta conectado, usando o access token dele (nunca o da PSI4).
     *
     * @param  array{items: array, external_reference: string, notification_url: string, payment_methods?: array, back_urls?: array, auto_return?: string}  $body
     * @return array{id: string, init_point: string}
     */
    public function createPreference(string $sellerAccessToken, array $body): array
    {
        $response = $this->send(fn () => $this->baseClient()
            ->withToken($sellerAccessToken)
            ->post('/checkout/preferences', $body));

        $this->assertSuccessful($response, 'payments.order_rejected');

        $id = $response->json('id');
        $initPoint = $response->json('init_point');

        if (! is_string($id) || $id === '' || ! is_string($initPoint) || $initPoint === '') {
            $this->registerFailure(
                'Resposta inesperada da API do Mercado Pago ao criar a preference (campos ausentes ou em formato inválido).',
                ['status' => $response->status(), 'response' => Str::limit($response->body(), 2000)],
            );

            throw new \UnexpectedValueException('invalid_response');
        }

        return ['id' => $id, 'init_point' => $initPoint];
    }

    /**
     * Atualiza a preference (`/checkout/preferences/{id}`) de uma cobrança já
     * gerada, mantendo o mesmo link de pagamento (`init_point`) enviado ao paciente.
     *
     * @param  array{items: array, external_reference: string, notification_url: string, payment_methods?: array, back_urls?: array, auto_return?: string}  $body
     */
    public function updatePreference(string $sellerAccessToken, string $preferenceId, array $body): void
    {
        $response = $this->send(fn () => $this->baseClient()
            ->withToken($sellerAccessToken)
            ->put('/checkout/preferences/'.rawurlencode($preferenceId), $body));

        $this->assertSuccessful($response, 'payments.order_update_rejected');
    }

    /**
     * Estorna integralmente um pagamento (`/v1/payments/{id}/refunds`) usando o
     * access token do terapeuta dono da cobrança. A chave de idempotência
     * evita estornar duas vezes em caso de clique duplo ou nova tentativa.
     *
     * @return array{id: int|string, status: string}
     */
    public function refundPayment(string $sellerAccessToken, string $paymentId, string $idempotencyKey): array
    {
        $response = $this->send(fn () => $this->baseClient()
            ->withToken($sellerAccessToken)
            ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
            ->post('/v1/payments/'.rawurlencode($paymentId).'/refunds', (object) []));

        $this->assertSuccessful($response, 'payments.refund_rejected');

        return [
            'id' => $response->json('id'),
            'status' => (string) $response->json('status', ''),
        ];
    }

    private function parseTokenResponse(Response $response): array
    {
        $accessToken = $response->json('access_token');
        $userId = $response->json('user_id');

        if (! is_string($accessToken) || $accessToken === '' || ! is_int($userId)) {
            $this->registerFailure(
                'Resposta inesperada da API do Mercado Pago ao trocar o code por token (campos ausentes ou em formato inválido).',
                // O corpo pode conter tokens válidos: nunca gravá-lo no log
                ['status' => $response->status(), 'response' => '*** (corpo omitido: pode conter credenciais)'],
            );

            throw new \UnexpectedValueException('invalid_response');
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => is_string($response->json('refresh_token')) ? $response->json('refresh_token') : null,
            'expires_in' => (int) $response->json('expires_in', 0),
            'user_id' => $userId,
            'public_key' => is_string($response->json('public_key')) ? $response->json('public_key') : null,
            'scope' => is_string($response->json('scope')) ? $response->json('scope') : null,
        ];
    }

    private function baseClient(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout((int) config('mercadopago.timeout'))
            ->beforeSending(function (Request $request) {
                $this->lastRequest = $request;
                $this->logDebugRequest($request);
            });
    }

    /**
     * Loga o cURL completo da requisição (headers e body sem máscara, incluindo
     * `Authorization`, `client_secret`, `code` e `refresh_token`) quando
     * `mercadopago.debug` estiver ativo.
     *
     * Uso exclusivo de depuração local: nunca habilitar em produção.
     */
    private function logDebugRequest(Request $request): void
    {
        if (! config('mercadopago.debug')) {
            return;
        }

        Log::debug('[MercadoPago Connect] '.$this->buildCurl($request, mask: false));
    }

    private function send(\Closure $call): Response
    {
        $this->lastRequest = null;

        try {
            $response = $call();

            $this->logDebugResponse($response);

            return $response;
        } catch (\Throwable $e) {
            report($e);

            $this->registerFailure($e->getMessage(), []);

            throw $e;
        }
    }

    /**
     * Loga status e corpo completo (sem máscara) da resposta do Mercado Pago.
     *
     * Uso exclusivo de depuração: nunca habilitar em produção.
     */
    private function logDebugResponse(Response $response): void
    {
        if (! config('mercadopago.debug')) {
            return;
        }

        Log::debug('[MercadoPago Connect] response '.$response->status().': '.$response->body());
    }

    private function registerFailure(string $message, array $data): void
    {
        $request = $this->lastRequest;

        if ($request === null) {
            return;
        }

        RegisterLog::dispatchSync([
            'message' => $message,
            'data' => $data + ['curl' => $this->buildCurl($request, mask: true)],
            'route' => $request->url(),
            'method' => $request->method(),
        ]);
    }

    /**
     * Monta o cURL equivalente da requisição. Com `$mask = true` (usado no log
     * de falha via `RegisterLog`), oculta o header `Authorization` e os campos
     * `client_secret`, `code` e `refresh_token` do corpo das chamadas OAuth
     * (`application/x-www-form-urlencoded`); com `$mask = false` (usado no log
     * de debug), tudo vai em texto puro.
     */
    private function buildCurl(Request $request, bool $mask): string
    {
        $parts = ['curl -X '.$request->method().' '.escapeshellarg($request->url())];

        foreach ($request->headers() as $name => $values) {
            if (in_array(strtolower($name), ['content-length', 'host', 'user-agent'], true)) {
                continue;
            }

            $lower = strtolower($name);
            $value = $mask && $lower === 'authorization' ? 'Bearer ***' : implode(', ', $values);
            $parts[] = '-H '.escapeshellarg($name.': '.$value);
        }

        if ($request->body() !== '') {
            $body = $mask ? $this->maskSecrets($request->body()) : $request->body();
            $parts[] = '--data '.escapeshellarg($body);
        }

        return implode(' ', $parts);
    }

    /**
     * Mascara `client_secret`, `code` e `refresh_token` enviados no corpo das
     * chamadas OAuth (`application/x-www-form-urlencoded`) antes de registrar
     * o cURL no log de falhas.
     */
    private function maskSecrets(string $body): string
    {
        return (string) preg_replace(
            '/(client_secret|code|refresh_token)=[^&]*/',
            '$1=***',
            $body,
        );
    }

    private function assertSuccessful(Response $response, string $rejectedMessage): void
    {
        if (! $response->failed()) {
            return;
        }

        $this->registerFailure(
            'Falha na requisição à API do Mercado Pago (HTTP '.$response->status().')',
            ['status' => $response->status(), 'response' => Str::limit($response->body(), 2000)],
        );

        $exception = ($response->clientError() && ! in_array($response->status(), self::RETRYABLE_CLIENT_ERRORS, true))
            ? new \DomainException(__($rejectedMessage))
            : $response->toException();

        throw $exception;
    }
}
