<?php

declare(strict_types=1);

namespace App\Http\Requests\ScaleApplications;

use App\Enums\ScaleEnum;
use App\Helpers\DateTimeHelper;
use App\Helpers\StringHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'string'],
            'scale' => ['required', new Enum(ScaleEnum::class)],
            'applied_on' => ['required', 'date_format:d/m/Y', 'before_or_equal:today'],
            'content_path' => ['required', 'string', 'max:255', 'regex:/^scale-applications\/[A-Za-z0-9._-]+$/'],
        ];
    }

    public function toInsert(): array
    {
        return [
            'patient_id' => (int) StringHelper::decrypt($this->input('patient_id')),
            'scale' => $this->input('scale'),
            'applied_on' => DateTimeHelper::convertToDatabase($this->input('applied_on'), 'Y-m-d'),
            'content_path' => $this->input('content_path'),
        ];
    }
}
