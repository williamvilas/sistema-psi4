<?php

declare(strict_types=1);

namespace App\Http\Requests\Agenda;

use App\Helpers\DateTimeHelper;
use Illuminate\Foundation\Http\FormRequest;

class AvailableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:d/m/Y'],
        ];
    }

    public function getDate(): ?string
    {
        return DateTimeHelper::convertToDatabase($this->input('date'), 'Y-m-d');
    }
}
