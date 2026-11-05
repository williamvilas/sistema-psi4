<?php

declare(strict_types=1);

namespace App\Http\Requests\MedicalRecords;

use App\Http\Requests\CustomRequest;

class EditRequest extends CustomRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required'],
        ];
    }
}
