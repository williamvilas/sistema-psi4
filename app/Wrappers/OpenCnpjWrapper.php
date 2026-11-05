<?php

declare(strict_types=1);

namespace App\Wrappers;

use App\Contracts\CnpjLookup;
use App\Jobs\RegisterLog;
use Canducci\OpenCnpj\ICnpjService;

/**
 * Wrapper de consulta de empresa por CNPJ no OpenCNPJ (https://opencnpj.org),
 * usando a biblioteca fulviocanducci/opencnpj. A biblioteca executa a
 * requisição por conta própria e não expõe o status HTTP, então a falha é
 * registrada com o cURL equivalente montado a partir da URL conhecida.
 */
class OpenCnpjWrapper implements CnpjLookup
{
    private const BASE_URL = 'https://api.opencnpj.org';

    public function __construct(private readonly ICnpjService $cnpjService)
    {
    }

    public function findByCnpj(string $cnpj): ?array
    {
        $response = $this->cnpjService->get($cnpj);

        if (! $response->isValid()) {
            $exception = $response->getException();

            $this->registerFailure(
                $exception?->getMessage() ?: 'Falha na requisição à API do OpenCNPJ',
                $cnpj,
            );

            throw $exception ?? new \RuntimeException('Falha na requisição à API do OpenCNPJ');
        }

        $company = $response->getCompany()?->toArray();

        // CNPJ inexistente volta como HTTP 404 `{"error":"not found"}`, que a
        // biblioteca converte em uma empresa sem nenhum dado preenchido
        if (empty($company) || empty($company['cnpj'])) {
            return null;
        }

        return $company;
    }

    private function registerFailure(string $message, string $cnpj): void
    {
        $url = self::BASE_URL.'/'.rawurlencode($cnpj);

        RegisterLog::dispatchSync([
            'message' => $message,
            'data' => ['curl' => 'curl -X GET '.escapeshellarg($url).' -H '.escapeshellarg('Accept: application/json')],
            'route' => $url,
            'method' => 'GET',
        ]);
    }
}
