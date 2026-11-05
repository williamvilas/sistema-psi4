<?php

declare(strict_types=1);

namespace App\Wrappers;

use App\Contracts\ZipcodeLookup;
use App\Jobs\RegisterLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Wrapper de consulta de endereço por CEP no ViaCEP (https://viacep.com.br).
 */
class ViaCepWrapper implements ZipcodeLookup
{
    private const BASE_URL = 'https://viacep.com.br/ws';

    private const CONNECT_TIMEOUT = 3;

    private const TIMEOUT = 5;

    private ?Request $lastRequest = null;

    public function findByZipcode(string $zipcode): ?array
    {
        $this->lastRequest = null;

        try {
            $response = Http::acceptJson()
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->timeout(self::TIMEOUT)
                ->beforeSending(function (Request $request) {
                    $this->lastRequest = $request;
                })
                ->get(self::BASE_URL.'/'.rawurlencode($zipcode).'/json/');
        } catch (ConnectionException $e) {
            $this->registerFailure($e->getMessage(), []);

            throw $e;
        }

        // ViaCEP responde 400 para CEP com formato inválido: tratado como não encontrado
        if ($response->status() === 400) {
            return null;
        }

        if ($response->failed()) {
            $this->registerFailure(
                'Falha na requisição à API do ViaCEP (HTTP '.$response->status().')',
                ['status' => $response->status(), 'response' => Str::limit($response->body(), 2000)],
            );

            throw $response->toException();
        }

        return $this->parse($response);
    }

    /**
     * Traduz a resposta do ViaCEP para o formato do contrato. CEP inexistente
     * volta com HTTP 200 e `{"erro": true}`.
     */
    private function parse(Response $response): ?array
    {
        $json = $response->json();

        if (! is_array($json)) {
            $this->registerFailure(
                'Resposta inesperada da API do ViaCEP (corpo em formato inválido).',
                ['status' => $response->status(), 'response' => Str::limit($response->body(), 2000)],
            );

            throw new \UnexpectedValueException('invalid_response');
        }

        if (! empty($json['erro'])) {
            return null;
        }

        return [
            'zipcode' => preg_replace('/\D/', '', (string) ($json['cep'] ?? '')),
            'street' => (string) ($json['logradouro'] ?? ''),
            'complement' => (string) ($json['complemento'] ?? ''),
            'neighborhood' => (string) ($json['bairro'] ?? ''),
            'city' => (string) ($json['localidade'] ?? ''),
            'state' => (string) ($json['uf'] ?? ''),
            'ibge' => (string) ($json['ibge'] ?? ''),
        ];
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
     * cURL equivalente da requisição. A API é pública e sem credenciais, então
     * não há o que mascarar.
     */
    private function buildCurl(Request $request): string
    {
        $parts = ['curl -X '.$request->method().' '.escapeshellarg($request->url())];

        foreach ($request->headers() as $name => $values) {
            if (in_array(strtolower($name), ['content-length', 'host', 'user-agent'], true)) {
                continue;
            }

            $parts[] = '-H '.escapeshellarg($name.': '.implode(', ', $values));
        }

        return implode(' ', $parts);
    }
}
