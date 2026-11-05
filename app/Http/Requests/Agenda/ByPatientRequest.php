<?php

declare(strict_types=1);

namespace App\Http\Requests\Agenda;

use App\Helpers\StringHelper;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class ByPatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'string'],
            'start' => ['nullable', 'date_format:d/m/Y'],
            'end' => ['nullable', 'date_format:d/m/Y'],
        ];
    }

    public function getPatientId(): int
    {
        return (int) StringHelper::decrypt($this->input('patient_id'));
    }

    public function getStart(string $timezone): ?Carbon
    {
        return $this->filled('start')
            ? Carbon::createFromFormat('d/m/Y', $this->input('start'), $timezone)->startOfDay()->utc()
            : null;
    }

    public function getEnd(string $timezone): ?Carbon
    {
        return $this->filled('end')
            ? Carbon::createFromFormat('d/m/Y', $this->input('end'), $timezone)->endOfDay()->utc()
            : null;
    }
}
