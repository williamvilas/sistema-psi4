<?php

declare(strict_types=1);

namespace App\Http\Requests\Agenda;

use App\Enums\AgendaTypeEnum;
use App\Helpers\DateTimeHelper;
use App\Helpers\StringHelper;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required'],
            'type' => ['required', 'in:'.implode(',', AgendaTypeEnum::values())],
            'date' => ['required', 'date_format:d/m/Y'],
            'link' => ['nullable', 'url'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'timer' => ['required', 'array', 'min:1', 'max:20'],
            'timer.*' => ['required', 'distinct', 'date_format:H:i'],
        ];
    }

    public function toInsert(): array
    {
        return [
            'patient_id' => StringHelper::decrypt($this->input('patient_id')),
            'date' => DateTimeHelper::convertToDatabase($this->input('date'), 'Y-m-d'),
            'room_link' => $this->input('link'),
            'price' => $this->input('price'),
            'timer' => $this->input('timer'),
            'type' => $this->input('type'),
        ];
    }
}
