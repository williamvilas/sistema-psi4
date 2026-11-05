<?php

declare(strict_types=1);

namespace App\Wrappers;

use App\Jobs\RegisterLog;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Wrapper para o Google Calendar de cada psicólogo: OAuth (autorização,
 * troca/renovação/revogação de token) e criação/atualização/remoção de eventos
 * na agenda dele, usando o token do próprio psicólogo.
 */
class GoogleCalendarWrapper
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    private const API_URL = 'https://www.googleapis.com/calendar/v3';

    private const CONNECT_TIMEOUT = 5;

    private const RETRYABLE_CLIENT_ERRORS = [408, 429];

    // Evento inexistente (apagado no Google) ou já apagado
    private const GONE_STATUSES = [404, 410];

    private ?Request $lastRequest = null;

    /**
     * `access_type=offline` + `prompt=consent` garantem o refresh token mesmo
     * quando o psicólogo já havia autorizado o app antes.
     */
    public function buildAuthorizationUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => (string) config('google-calendar.oauth.client_id'),
            'redirect_uri' => (string) config('google-calendar.oauth.redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', config('google-calendar.scopes')),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, scope: ?string, google_user_id: ?string, email: ?string}
     */
    public function exchangeAuthorizationCode(string $code): array
    {
        $response = $this->send(fn () => $this->baseClient()
            ->asForm()
            ->post(self::TOKEN_URL, [
                'grant_type' => 'authorization_code',
                'client_id' => (string) config('google-calendar.oauth.client_id'),
                'client_secret' => (string) config('google-calendar.oauth.client_secret'),
                'code' => $code,
                'redirect_uri' => (string) config('google-calendar.oauth.redirect_uri'),
            ]));

        $this->assertSuccessful($response, 'google-calendar.oauth_failed');

        return $this->parseTokenResponse($response);
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, scope: ?string, google_user_id: ?string, email: ?string}
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $response = $this->send(fn () => $this->baseClient()
            ->asForm()
            ->post(self::TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'client_id' => (string) config('google-calendar.oauth.client_id'),
                'client_secret' => (string) config('google-calendar.oauth.client_secret'),
                'refresh_token' => $refreshToken,
            ]));

        $this->assertSuccessful($response, 'google-calendar.oauth_failed');

        return $this->parseTokenResponse($response);
    }

    /**
     * Revoga o acesso concedido (best-effort: a desconexão local não depende do resultado).
     */
    public function revokeToken(string $token): void
    {
        try {
            $response = $this->send(fn () => $this->baseClient()
                ->asForm()
                ->post(self::REVOKE_URL, ['token' => $token]));

            if ($response->failed()) {
                $this->registerFailure(
                    'Falha ao revogar o acesso do Google Calendar (HTTP '.$response->status().')',
                    ['status' => $response->status(), 'response' => Str::limit($response->body(), 2000)],
                );
            }
        } catch (\Throwable) {
            // já registrado em send(); revogar é opcional
        }
    }

    /**
     * Calendários em que o psicólogo pode criar eventos.
     *
     * @return array<int, array{id: string, summary: string, primary: bool}>
     */
    public function listWritableCalendars(string $accessToken): array
    {
        $response = $this->send(fn () => $this->apiClient($accessToken)
            ->get('/users/me/calendarList', ['minAccessRole' => 'writer', 'maxResults' => 100]));

        $this->assertSuccessful($response, 'google-calendar.request_rejected');

        return collect($response->json('items', []))
            ->filter(fn ($item) => is_array($item) && is_string($item['id'] ?? null))
            ->map(fn (array $item) => [
                'id' => $item['id'],
                'summary' => (string) ($item['summaryOverride'] ?? $item['summary'] ?? $item['id']),
                'primary' => (bool) ($item['primary'] ?? false),
            ])
            ->values()
            ->all();
    }

    /**
     * Intervalos ocupados (livre/ocupado, sem detalhes dos eventos) dos calendários informados.
     *
     * @param  string[]  $calendarIds
     * @return array<int, array{start: string, end: string}> intervalos em ISO 8601 (UTC)
     */
    public function queryBusyIntervals(string $accessToken, array $calendarIds, Carbon $startUtc, Carbon $endUtc): array
    {
        $response = $this->send(fn () => $this->apiClient($accessToken)
            ->post('/freeBusy', [
                'timeMin' => $startUtc->copy()->utc()->toRfc3339String(),
                'timeMax' => $endUtc->copy()->utc()->toRfc3339String(),
                'timeZone' => 'UTC',
                'items' => array_map(fn (string $id) => ['id' => $id], $calendarIds),
            ]));

        $this->assertSuccessful($response, 'google-calendar.request_rejected');

        $busy = [];

        foreach ((array) $response->json('calendars', []) as $calendarId => $calendar) {
            // Calendário com erro (ex.: inexistente) é ignorado, mas registrado: sem isso a agenda
            // aparece toda livre na listagem e o horário só é recusado na hora de agendar
            if (! empty($calendar['errors'])) {
                RegisterLog::dispatchSync([
                    'message' => 'Google freeBusy retornou erro para o calendário',
                    'data' => ['calendar' => $calendarId, 'errors' => $calendar['errors'], 'timeMin' => $startUtc->toRfc3339String(), 'timeMax' => $endUtc->toRfc3339String()],
                    'route' => 'google-calendar/freeBusy',
                    'method' => 'POST',
                ]);
            }

            foreach ((array) ($calendar['busy'] ?? []) as $interval) {
                if (is_string($interval['start'] ?? null) && is_string($interval['end'] ?? null)) {
                    $busy[] = ['start' => $interval['start'], 'end' => $interval['end']];
                }
            }
        }

        return $busy;
    }

    /**
     * Cria o evento (com o `id` definido pelo cliente, o que torna o reenvio
     * idempotente). Retorna null quando o evento já existe (409), para o
     * chamador atualizá-lo.
     *
     * @return array{id: string}|null
     */
    public function insertEvent(string $accessToken, string $calendarId, array $event, bool $notifyAttendees): ?array
    {
        $response = $this->send(fn () => $this->apiClient($accessToken)
            ->post($this->eventsPath($calendarId).'?'.$this->queryString($notifyAttendees), $event));

        // Reenvio de um evento já criado
        if ($response->status() === 409) {
            return null;
        }

        $this->assertSuccessful($response, 'google-calendar.request_rejected');

        return $this->parseEventResponse($response);
    }

    /**
     * Atualiza o evento. Retorna null quando ele não existe mais no Google.
     *
     * @return array{id: string}|null
     */
    public function patchEvent(string $accessToken, string $calendarId, string $eventId, array $event, bool $notifyAttendees): ?array
    {
        $response = $this->send(fn () => $this->apiClient($accessToken)
            ->patch($this->eventsPath($calendarId, $eventId).'?'.$this->queryString($notifyAttendees), $event));

        if (in_array($response->status(), self::GONE_STATUSES, true)) {
            return null;
        }

        $this->assertSuccessful($response, 'google-calendar.request_rejected');

        return $this->parseEventResponse($response);
    }

    /**
     * Remove o evento; evento já inexistente conta como sucesso.
     */
    public function deleteEvent(string $accessToken, string $calendarId, string $eventId, bool $notifyAttendees): void
    {
        $response = $this->send(fn () => $this->apiClient($accessToken)
            ->delete($this->eventsPath($calendarId, $eventId).'?'.$this->queryString($notifyAttendees)));

        if (in_array($response->status(), self::GONE_STATUSES, true)) {
            return;
        }

        $this->assertSuccessful($response, 'google-calendar.request_rejected');
    }

    private function eventsPath(string $calendarId, ?string $eventId = null): string
    {
        $path = '/calendars/'.rawurlencode($calendarId).'/events';

        return $eventId === null ? $path : $path.'/'.rawurlencode($eventId);
    }

    private function queryString(bool $notifyAttendees): string
    {
        return http_build_query(['sendUpdates' => $notifyAttendees ? 'all' : 'none']);
    }

    /**
     * @return array{id: string}
     */
    private function parseEventResponse(Response $response): array
    {
        $id = $response->json('id');

        if (! is_string($id) || $id === '') {
            $this->registerFailure(
                'Resposta inesperada da API do Google Calendar ao salvar o evento (campos ausentes ou em formato inválido).',
                ['status' => $response->status(), 'response' => Str::limit($response->body(), 2000)],
            );

            throw new \UnexpectedValueException('invalid_response');
        }

        return ['id' => $id];
    }

    private function parseTokenResponse(Response $response): array
    {
        $accessToken = $response->json('access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            $this->registerFailure(
                'Resposta inesperada da API do Google ao obter o token (campos ausentes ou em formato inválido).',
                // O corpo pode conter tokens válidos: nunca gravá-lo no log
                ['status' => $response->status(), 'response' => '*** (corpo omitido: pode conter credenciais)'],
            );

            throw new \UnexpectedValueException('invalid_response');
        }

        $claims = $this->decodeIdToken($response->json('id_token'));

        return [
            'access_token' => $accessToken,
            'refresh_token' => is_string($response->json('refresh_token')) ? $response->json('refresh_token') : null,
            'expires_in' => (int) $response->json('expires_in', 0),
            'scope' => is_string($response->json('scope')) ? $response->json('scope') : null,
            'google_user_id' => isset($claims['sub']) ? (string) $claims['sub'] : null,
            'email' => isset($claims['email']) ? (string) $claims['email'] : null,
        ];
    }

    /**
     * Lê os claims do `id_token`, recebido direto do endpoint de token do Google
     * por TLS (por isso a assinatura não precisa ser verificada).
     */
    private function decodeIdToken(mixed $idToken): array
    {
        if (! is_string($idToken)) {
            return [];
        }

        $parts = explode('.', $idToken);
        $payload = $parts[1] ?? null;

        if ($payload === null) {
            return [];
        }

        $claims = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);

        return is_array($claims) ? $claims : [];
    }

    private function baseClient(): PendingRequest
    {
        return Http::acceptJson()
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout((int) config('google-calendar.timeout'))
            ->beforeSending(function (Request $request) {
                $this->lastRequest = $request;
            });
    }

    private function apiClient(string $accessToken): PendingRequest
    {
        return $this->baseClient()
            ->baseUrl(self::API_URL)
            ->asJson()
            ->withToken($accessToken);
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

    /**
     * cURL equivalente da requisição, com `Authorization` e os segredos do
     * corpo OAuth (`client_secret`, `code`, `refresh_token`, `token`) mascarados.
     * O corpo dos eventos é omitido: contém dados de pacientes.
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
            $isForm = str_contains(strtolower($request->header('Content-Type')[0] ?? ''), 'x-www-form-urlencoded');
            $body = $isForm
                ? (string) preg_replace('/(client_secret|code|refresh_token|token)=[^&]*/', '$1=***', $request->body())
                : '*** (corpo omitido: pode conter dados de pacientes)';

            $parts[] = '--data '.escapeshellarg($body);
        }

        return implode(' ', $parts);
    }

    private function assertSuccessful(Response $response, string $rejectedMessage): void
    {
        if (! $response->failed()) {
            return;
        }

        $this->registerFailure(
            'Falha na requisição à API do Google (HTTP '.$response->status().')',
            ['status' => $response->status(), 'response' => Str::limit($response->body(), 2000)],
        );

        // O Google devolve 403 para estouro de cota (transitório): deve ser tentado novamente
        $rateLimited = $response->status() === 403 && str_contains($response->body(), 'ateLimitExceeded');

        $exception = ($response->clientError() && ! $rateLimited && ! in_array($response->status(), self::RETRYABLE_CLIENT_ERRORS, true))
            ? new \DomainException(__($rejectedMessage))
            : $response->toException();

        throw $exception;
    }
}
