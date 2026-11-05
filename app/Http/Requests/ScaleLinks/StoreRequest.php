<?php

declare(strict_types=1);

namespace App\Http\Requests\ScaleLinks;

use App\Enums\ScaleEnum;
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
        ];
    }

    public function getPatientId(): int
    {
        return (int) StringHelper::decrypt($this->input('patient_id'));
    }

    public function getScale(): string
    {
        return (string) $this->input('scale');
    }
}
