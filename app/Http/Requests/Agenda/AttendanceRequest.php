<?php

declare(strict_types=1);

namespace App\Http\Requests\Agenda;

use App\Enums\AgendaStatusEnum;
use App\Helpers\StringHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'string'],
            'status' => ['required', 'string', Rule::in([AgendaStatusEnum::COMPLETED->value, AgendaStatusEnum::NO_SHOW->value])],
        ];
    }

    public function getId(): int
    {
        return (int) StringHelper::decrypt($this->input('id'));
    }

    public function getStatus(): AgendaStatusEnum
    {
        return AgendaStatusEnum::from($this->input('status'));
    }
}
