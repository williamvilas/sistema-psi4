<?php

declare(strict_types=1);

namespace App\Http\Requests\GoogleCalendar;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'invite_patient' => $this->boolean('invite_patient'),
            'block_busy' => $this->boolean('block_busy'),
        ]);
    }

    public function rules(): array
    {
        return [
            'calendar_id' => ['required', 'string', 'max:255'],
            'invite_patient' => ['boolean'],
            'block_busy' => ['boolean'],
        ];
    }

    public function getCalendarId(): string
    {
        return (string) $this->validated('calendar_id');
    }

    public function getInvitePatient(): bool
    {
        return (bool) $this->validated('invite_patient');
    }

    public function getBlockBusy(): bool
    {
        return (bool) $this->validated('block_busy');
    }
}
