<?php

declare(strict_types=1);

namespace App\Http\Requests\Patients;

use App\Http\Requests\CustomRequest;

class ExistsRequest extends CustomRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['nullable'],
        ];
    }

    public function getPhone(): ?string
    {
        return $this->query('phone');
    }
}
