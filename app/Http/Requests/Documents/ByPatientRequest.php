<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\Helpers\DateTimeHelper;
use App\Helpers\StringHelper;
use App\Http\Requests\CustomRequest;
use Carbon\Carbon;

class ByPatientRequest extends CustomRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['start', 'end'] as $field) {
            if ($this->filled($field)) {
                $this->merge([
                    $field => DateTimeHelper::convertToDatabase($this->input($field), 'Y-m-d') ?? $this->input($field),
                ]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
        ];
    }

    public function getPatientId(): int
    {
        return (int) StringHelper::decrypt($this->route('patient_id'));
    }

    public function getStart(string $timezone): ?Carbon
    {
        return $this->filled('start')
            ? Carbon::createFromFormat('Y-m-d', $this->input('start'), $timezone)->startOfDay()->utc()
            : null;
    }

    public function getEnd(string $timezone): ?Carbon
    {
        return $this->filled('end')
            ? Carbon::createFromFormat('Y-m-d', $this->input('end'), $timezone)->endOfDay()->utc()
            : null;
    }

    public function getPerPage(): int
    {
        $perPage = $this->query('per_page');

        if ($perPage) {
            return (int) $perPage;
        }

        return 3;
    }
}
