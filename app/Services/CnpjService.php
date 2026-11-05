<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CnpjLookup;
use App\Helpers\CacheHelper;

class CnpjService
{
    private const CACHE_TTL_SECONDS = 86400;

    public function __construct(private readonly CnpjLookup $cnpjLookup)
    {
    }

    /**
     * Busca os dados da empresa pelo CNPJ, com cache de 24 horas por CNPJ
     * (inclusive para CNPJ inexistente, evitando reconsultar o provedor).
     *
     * @return array<string, mixed>
     *
     * @throws \DomainException quando o CNPJ não é encontrado
     */
    public function findByCnpj(string $cnpj): array
    {
        $key = 'cnpj:'.$cnpj;

        $cached = CacheHelper::get($key);

        if ($cached === null) {
            $cached = ['company' => $this->cnpjLookup->findByCnpj($cnpj)];

            CacheHelper::put($key, $cached, self::CACHE_TTL_SECONDS);
        }

        if ($cached['company'] === null) {
            throw new \DomainException(__('cnpj.not_found'));
        }

        return $cached['company'];
    }
}
