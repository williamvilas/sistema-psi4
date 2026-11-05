<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum PaymentMethodEnum: string
{
    use EnumTrait;

    case PIX = 'pix';
    case CARD = 'card';

    public function getText(): string
    {
        return __('enum.payment_method.'.$this->value);
    }

    /**
     * Subtítulo curto exibido no card de seleção da forma de pagamento.
     */
    public function description(): string
    {
        return __('enum.payment_method_description.'.$this->value);
    }

    /**
     * `payment_type_id`(s) do Mercado Pago cobertos por esta forma de pagamento.
     * Usado para montar `payment_methods.excluded_payment_types` na preference
     * do Checkout Pro, restringindo o checkout às formas escolhidas pelo terapeuta.
     *
     * No Brasil o Pix é oferecido dentro do tipo `bank_transfer` do Mercado Pago
     * (não existe um `payment_type_id` exclusivo para "pix").
     *
     * @return string[]
     */
    public function toMercadoPagoPaymentTypeIds(): array
    {
        return match ($this) {
            self::PIX => ['bank_transfer'],
            self::CARD => ['credit_card', 'debit_card', 'prepaid_card'],
        };
    }

    /**
     * Todos os `payment_type_id` do Mercado Pago conhecidos pelo Checkout Pro.
     * Usado para calcular quais tipos devem ser excluídos quando uma forma de
     * pagamento não é aceita pelo terapeuta.
     *
     * @return string[]
     */
    public static function allMercadoPagoPaymentTypeIds(): array
    {
        return ['ticket', 'atm', 'credit_card', 'debit_card', 'prepaid_card', 'bank_transfer', 'digital_wallet', 'digital_currency'];
    }

    /**
     * O Mercado Pago exige uma lista de objetos `{"id": "..."}` em
     * `payment_methods.excluded_payment_types` — enviar strings soltas
     * retorna `400 invalid_field_type` na criação da preference.
     *
     * @param  string[]  $allowedMethods  valores de {@see self} aceitos no checkout
     * @return array<int, array{id: string}>
     */
    public static function excludedMercadoPagoPaymentTypes(array $allowedMethods): array
    {
        $allowed = [];

        foreach ($allowedMethods as $method) {
            $enum = self::tryFrom($method);

            if ($enum) {
                $allowed = [...$allowed, ...$enum->toMercadoPagoPaymentTypeIds()];
            }
        }

        $excluded = array_values(array_diff(self::allMercadoPagoPaymentTypeIds(), $allowed));

        return array_map(static fn (string $type): array => ['id' => $type], $excluded);
    }

    public function icon(): string
    {
        return match ($this) {
            self::PIX => 'fa-brands fa-pix',
            self::CARD => 'fas fa-credit-card',
        };
    }
}
