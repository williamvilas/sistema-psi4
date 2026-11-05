<?php

declare(strict_types=1);

namespace App\Helpers;

use NumberToWords\NumberToWords;

class NumberHelper
{
    public static function clean($number): float
    {
        // Remove non-numeric characters except comma and minus
        $cleaned = preg_replace('/[^0-9,-]/', '', $number);
        // Replace comma with dot for decimal
        $cleaned = str_replace(',', '.', $cleaned);
        // Remove thousands separators (dots in BR format)
        $cleaned = str_replace('.', '', $cleaned);

        return (float) $cleaned;
    }

    public static function onlyNumbers(string $string)
    {
        return preg_replace('/[^0-9]/', '', $string);
    }

    /**
     * Telefone brasileiro com DDD (como salvo no cadastro) no formato internacional aceito pelo WhatsApp: 55 + DDD + número.
     */
    public static function toWhatsAppNumber(string $phone): string
    {
        $digits = self::onlyNumbers($phone);

        return strlen($digits) <= 11 ? '55'.$digits : $digits;
    }

    /**
     * Número recebido do WhatsApp (wa_id) no formato do cadastro (DDD + número). O WhatsApp devolve
     * alguns celulares brasileiros sem o nono dígito: ele é recolocado para bater com o cadastro.
     */
    public static function fromWhatsAppNumber(string $waId): string
    {
        $digits = self::onlyNumbers($waId);

        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }

        // DDD + 8 dígitos começando por 6-9 é celular sem o nono dígito
        if (strlen($digits) === 10 && in_array($digits[2], ['6', '7', '8', '9'], true)) {
            $digits = substr($digits, 0, 2).'9'.substr($digits, 2);
        }

        return $digits;
    }

    public static function numberToWords(int $number): string
    {
        $numberToWords = new NumberToWords();
        $numberTransformer = $numberToWords->getNumberTransformer('pt_BR');

        return $numberTransformer->toWords(number: $number);
    }
}
