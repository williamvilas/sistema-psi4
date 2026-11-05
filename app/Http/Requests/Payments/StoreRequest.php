<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Enums\PaymentMethodEnum;
use App\Helpers\DateTimeHelper;
use App\Helpers\StringHelper;
use App\Helpers\UserHelper;
use App\Traits\NormalizeAmountTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    use NormalizeAmountTrait;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => $this->normalizeAmount($this->input('amount')),
        ]);
    }

    public function rules(): array
    {
        $tomorrow = Carbon::now(UserHelper::getTimezone())->addDay()->format('d/m/Y');

        return [
            'patient_id' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'description' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date_format:d/m/Y', 'after_or_equal:'.$tomorrow],
            'payment_methods' => ['required', 'array', 'min:1'],
            'payment_methods.*' => ['required', Rule::enum(PaymentMethodEnum::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'due_date.after_or_equal' => 'O vencimento deve ser a partir de amanhã.',
        ];
    }

    public function toPayload(): array
    {
        return [
            'patient_id' => StringHelper::decrypt($this->input('patient_id')),
            'amount' => $this->input('amount'),
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'due_date' => DateTimeHelper::convertToDatabase($this->input('due_date'), 'Y-m-d'),
            'payment_methods' => array_values(array_unique($this->input('payment_methods'))),
        ];
    }
}
