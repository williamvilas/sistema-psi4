<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Enums\PaymentOrderStatusEnum;
use App\Helpers\StringHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaginateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(PaymentOrderStatusEnum::class)],
        ];
    }

    public function getPatientId(): ?int
    {
        $id = StringHelper::decrypt($this->input('patient_id'));

        return $id ? (int) $id : null;
    }
}
