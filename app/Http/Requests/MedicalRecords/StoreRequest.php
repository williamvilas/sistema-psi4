<?php

declare(strict_types=1);

namespace App\Http\Requests\MedicalRecords;

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
            'agenda_id' => ['nullable'],
            'patient_id' => ['required'],
            'title' => ['required', 'string', 'max:255'],
            'date_of_consultation' => ['required', 'date_format:d/m/Y'],
            'notes_path' => ['required', 'string', 'max:255', 'regex:/^medical-records\/[A-Za-z0-9._-]+$/'],
        ];
    }

    public function toInsert(): array
    {
        return [
            'agenda_id' => StringHelper::decrypt($this->input('agenda_id')),
            'patient_id' => StringHelper::decrypt($this->input('patient_id')),
            'title' => $this->input('title'),
            'notes_path' => $this->input('notes_path'),
            'date_of_consultation' => DateTimeHelper::convertToDatabase($this->input('date_of_consultation'), 'Y-m-d'),
        ];
    }
}
