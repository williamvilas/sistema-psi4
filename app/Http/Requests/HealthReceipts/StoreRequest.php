<?php

declare(strict_types=1);

namespace App\Http\Requests\HealthReceipts;

use App\Helpers\DateTimeHelper;
use App\Helpers\StringHelper;
use App\Helpers\UserHelper;
use App\Traits\NormalizeAmountTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

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
        $today = Carbon::now(UserHelper::getTimezone())->format('d/m/Y');

        return [
            'patient_id' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'paid_on' => ['required', 'date_format:d/m/Y', 'before_or_equal:'.$today],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'paid_on.before_or_equal' => 'A data do pagamento não pode ser no futuro.',
        ];
    }

    public function toPayload(): array
    {
        return [
            'patient_id' => (int) StringHelper::decrypt($this->input('patient_id')),
            'amount' => $this->input('amount'),
            'paid_on' => DateTimeHelper::convertToDatabase($this->input('paid_on'), 'Y-m-d'),
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
        ];
    }
}
