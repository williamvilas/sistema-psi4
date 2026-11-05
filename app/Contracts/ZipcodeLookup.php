<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contrato de consulta de endereço por CEP. Qualquer provedor (ViaCEP,
 * BrasilAPI, etc.) deve implementá-lo devolvendo sempre o mesmo formato, para
 * que trocar de serviço não altere o contrato do endpoint.
 */
interface ZipcodeLookup
{
    /**
     * @param  string  $zipcode  Somente dígitos (8)
     * @return array{zipcode: string, street: string, complement: string, neighborhood: string, city: string, state: string, ibge: string}|null null quando o CEP não existe
     */
    public function findByZipcode(string $zipcode): ?array;
}
