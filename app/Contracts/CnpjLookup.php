<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contrato de consulta de empresa por CNPJ. Qualquer provedor deve
 * implementá-lo devolvendo sempre o mesmo formato, para que trocar de serviço
 * não altere o contrato do endpoint.
 */
interface CnpjLookup
{
    /**
     * @param  string  $cnpj  Somente dígitos (14)
     * @return array<string, mixed>|null null quando o CNPJ não existe
     */
    public function findByCnpj(string $cnpj): ?array;
}
