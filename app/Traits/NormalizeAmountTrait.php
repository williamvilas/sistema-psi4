<?php

declare(strict_types=1);

namespace App\Traits;

trait NormalizeAmountTrait
{
    /**
     * Converte o valor mascarado no front ("R$ 1.250,00") para o formato numérico ("1250.00").
     */
    private function normalizeAmount(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $value = preg_replace('/[^\d,]/', '', $value);

        return str_replace(',', '.', $value);
    }
}
