<?php

declare(strict_types=1);

namespace App\Http\Requests\HealthReceipts;

use App\Enums\HealthReceiptStatusEnum;
use App\Helpers\DateTimeHelper;
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
            'status' => ['nullable', Rule::enum(HealthReceiptStatusEnum::class)],
            'start_date' => ['nullable', 'date_format:d/m/Y'],
            'end_date' => ['nullable', 'date_format:d/m/Y'],
        ];
    }

    public function toParams(): array
    {
        return [
            'status' => $this->input('status') ?: null,
            'start_date' => DateTimeHelper::convertToDatabase($this->input('start_date'), 'Y-m-d'),
            'end_date' => DateTimeHelper::convertToDatabase($this->input('end_date'), 'Y-m-d'),
        ];
    }

    public function getPatientId(): ?int
    {
        $id = StringHelper::decrypt($this->input('patient_id'));

        return $id ? (int) $id : null;
    }
}
