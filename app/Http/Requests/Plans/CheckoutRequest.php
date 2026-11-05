<?php

declare(strict_types=1);

namespace App\Http\Requests\Plans;

use App\Enums\PaymentMethodEnum;
use App\Enums\PlanCycleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', Rule::in(array_keys((array) config('plans.plans')))],
            'cycle' => ['nullable', 'string', Rule::in(array_column(PlanCycleEnum::cases(), 'value'))],
            'payment_method' => ['nullable', 'required_if:cycle,'.PlanCycleEnum::ANNUAL->value, 'string', Rule::in(array_column(PaymentMethodEnum::cases(), 'value'))],
        ];
    }

    public function attributes(): array
    {
        return [
            'plan' => 'plano',
            'cycle' => 'periodicidade',
            'payment_method' => 'forma de pagamento',
        ];
    }

    public function getPlan(): string
    {
        return (string) $this->input('plan');
    }

    public function getCycle(): string
    {
        return (string) $this->input('cycle', PlanCycleEnum::MONTHLY->value);
    }

    public function getPaymentMethod(): ?string
    {
        return $this->input('payment_method');
    }
}
