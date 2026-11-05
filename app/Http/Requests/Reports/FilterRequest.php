<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Helpers\DateTimeHelper;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class FilterRequest extends FormRequest
{
    private const MAX_MONTHS = 24;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'start_date' => DateTimeHelper::convertToDatabase($this->input('start_date'), 'Y-m-d') ?? $this->input('start_date'),
            'end_date' => DateTimeHelper::convertToDatabase($this->input('end_date'), 'Y-m-d') ?? $this->input('end_date'),
        ]);
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'track' => ['sometimes', 'boolean'],
        ];
    }

    public function shouldTrack(): bool
    {
        return $this->boolean('track');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = Carbon::createFromFormat('Y-m-d', $this->input('start_date'));
            $end = Carbon::createFromFormat('Y-m-d', $this->input('end_date'));

            if ($end->lt($start)) {
                $validator->errors()->add('end_date', __('reports.period_invalid'));

                return;
            }

            if ($end->gt($start->copy()->addMonths(self::MAX_MONTHS))) {
                $validator->errors()->add('end_date', __('reports.period_too_long'));
            }
        });
    }
}
