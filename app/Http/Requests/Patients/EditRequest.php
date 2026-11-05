<?php

declare(strict_types=1);

namespace App\Http\Requests\Patients;

use App\Http\Requests\CustomRequest;

class EditRequest extends CustomRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required'],
        ];
    }
}
