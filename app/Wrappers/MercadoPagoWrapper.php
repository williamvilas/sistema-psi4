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
 * Wrapper para a API do Mercado Pago usando a credencial da própria conta
 * Mercado Pago da PSI4 (assinatura do plano SaaS via `/preapproval`).
 */
class MercadoPagoWrapper
{
    private const BASE_URL = 'https://api.mercadopago.com';

    private const CONNECT_TIMEOUT = 5;

    private const RETRYABLE_CLIENT_ERRORS = [408, 429];

    private ?Request $lastRequest = null;

    /**
     * Cria uma assinatura (`/preapproval`) sem plano associado (contrato
     * `without-plan-pending`): o pagador finaliza o cadastro do cartão na
     * página hospedada do Mercado Pago (`init_point`).
     *
     * @param  array{reason: string, external_reference: string, payer_email: ?string, auto_recurring: array, back_url: string}  $body
     * @return array{id: string, status: string, init_point: string}
     */
    public function createSubscription(array $body): array
    {
        $response = $this->send(fn () => $this->client()
            ->post('/preapproval', $body + ['status' => 'pending']));

        $this->assertSuccessful($response, 'plans.checkout_failed');

        $id = $response->json('id');
        $initPoint = $response->json('init_point');

        if (! is_string($id) || $id === '' || ! is_string($initPoint) || $initPoint === '') {
            $this->registerFailure(
                'Resposta inesperada da API do Mercado Pago ao criar a assinatura (campos ausentes ou em formato inválido).',
                ['status' => $response->status(), 'response' => Str::limit($response->body(), 2000)],
            );

            throw new \UnexpectedValueException('invalid_response');
        }

        return [
            'id' => $id,
            'status' => (string) $response->json('status'),
            'init_point' => $initPoint,
        ];
    }

    /**
     * Cria a preference (`/checkout/preferences`) do plano anual: pagamento
     * único hospedado no Checkout Pro, na conta da própria PSI4.
     *
     * @return array{id: string, init_point: string}
     */
    public function createPreference(array $body): array
    {
        $response = $this->send(fn () => $this->client()->post('/checkout/preferences', $body));

        $this->assertSuccessful($response, 'plans.checkout_failed');

        $id = $response->json('id');
        $initPoint = $response->json('init_point');

        if (! is_string($id) || $id === '' || ! is_string($initPoint) || $initPoint === '') {
            $this->registerFailure(
                'Resposta inesperada da API do Mercado Pago ao criar a preference do plano (campos ausentes ou em formato inválido).',
                ['status' => $response->status(), 'response' => Str::limit($response->body(), 2000)],
            );

            throw new \UnexpectedValueException('invalid_response');
        }

        return ['id' => $id, 'init_point' => $initPoint];
    }

    /**
     * Cancela a assinatura (`/preapproval`): o Mercado Pago deixa de cobrar.
     * O acesso do usuário até o fim do ciclo pago é mantido pelo webhook.
     */
    public function cancelSubscription(string $preapprovalId): void
    {
        $response = $this->send(fn () => $this->client()
            ->put('/preapproval/'.rawurlencode($preapprovalId), ['status' => 'cancelled']));

        $this->assertSuccessful($response, 'plans.cancel_renewal_failed');
    }

    public function getSubscription(string $preapprovalId): array
    {
        $response = $this->send(fn () => $this->client()->get('/preapproval/'.rawurlencode($preapprovalId)));

        $this->assertSuccessful($response, 'plans.subscription_not_found');

        return (array) $response->json();
    }

    public function getAuthorizedPayment(string $authorizedPaymentId): array
    {
        $response = $this->send(fn () => $this->client()->get('/authorized_payments/'.rawurlencode($authorizedPaymentId)));

        $this->assertSuccessful($response, 'plans.subscription_not_found');

        return (array) $response->json();
    }

    /**
     * Busca um pagamento pelo id usando a credencial da própria PSI4.
     *
     * Usado para reconciliar cobranças criadas via Mercado Pago Connect em
     * nome de um terapeuta conectado: a aplicação Mercado Pago da PSI4 (dona
     * do OAuth usado para gerar o token do terapeuta) tem visibilidade de
     * leitura sobre os pagamentos criados através dela.
     */
    public function getPayment(string $paymentId): array
    {
        $response = $this->send(fn () => $this->client()->get('/v1/payments/'.rawurlencode($paymentId)));

        $this->assertSuccessful($response, 'payments.not_found');

        return (array) $response->json();
    }

    private function client(): PendingRequest
    {
        $token = (string) config('mercadopago.access_token');

        if ($token === '') {
            throw new \DomainException(__('payments.integration_not_configured'));
        }

        return Http::baseUrl(self::BASE_URL)
            ->withToken($token)
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
     * o token de `Authorization`) quando `mercadopago.debug` estiver ativo.
     *
     * Uso exclusivo de depuração local: nunca habilitar em produção.
     */
    private function logDebugRequest(Request $request): void
    {
        if (! config('mercadopago.debug')) {
            return;
        }

        Log::debug('[MercadoPago] '.$this->buildCurl($request, mask: false));
    }

    /**
     * Monta o cURL equivalente da requisição. Com `$mask = true` (usado no log
     * de falha via `RegisterLog`), o header `Authorization` é ocultado; com
     * `$mask = false` (usado no log de debug), o token vai em texto puro.
     */
    private function buildCurl(Request $request, bool $mask): string
    {
        $parts = ['curl -X '.$request->method().' '.escapeshellarg($request->url())];

        foreach ($request->headers() as $name => $values) {
            if (in_array(strtolower($name), ['content-length', 'host', 'user-agent'], true)) {
                continue;
            }

            $value = $mask && strtolower($name) === 'authorization' ? 'Bearer ***' : implode(', ', $values);
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
            'data' => $data + ['curl' => $this->buildCurl($request, mask: true)],
            'route' => $request->url(),
            'method' => $request->method(),
        ]);
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
