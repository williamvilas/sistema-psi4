<?php

declare(strict_types=1);

namespace App\Http\Requests\PublicBooking;

use App\Helpers\StringHelper;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'd' => ['required', 'string'],
        ];
    }

    public function getSummary(): ?array
    {
        $payload = StringHelper::decrypt($this->input('d'));
        $summary = $payload ? json_decode($payload, true) : null;

        return is_array($summary) ? $summary : null;
    }
}
