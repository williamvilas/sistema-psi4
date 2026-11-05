<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ZipcodeLookup;
use App\Helpers\CacheHelper;

class ZipcodeService
{
    private const CACHE_TTL_SECONDS = 7200;

    public function __construct(private readonly ZipcodeLookup $zipcodeLookup)
    {
    }

    /**
     * Busca o endereço do CEP, com cache de 2 horas por CEP (inclusive
     * para CEP inexistente, evitando reconsultar o provedor). Todos os dados
     * são devolvidos em caixa alta, preservando acentos (UTF-8).
     *
     * @return array{zipcode: string, street: string, complement: string, neighborhood: string, city: string, state: string, ibge: string}
     *
     * @throws \DomainException quando o CEP não é encontrado
     */
    public function findByZipcode(string $zipcode): array
    {
        $key = 'zipcode:'.$zipcode;

        $cached = CacheHelper::get($key);

        if ($cached === null) {
            $cached = ['address' => $this->zipcodeLookup->findByZipcode($zipcode)];

            CacheHelper::put($key, $cached, self::CACHE_TTL_SECONDS);
        }

        if ($cached['address'] === null) {
            throw new \DomainException(__('zipcode.not_found'));
        }

        return array_map(fn (string $value) => mb_strtoupper($value, 'UTF-8'), $cached['address']);
    }
}
