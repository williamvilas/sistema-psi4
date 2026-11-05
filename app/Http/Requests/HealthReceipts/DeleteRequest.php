<?php

declare(strict_types=1);

namespace App\Http\Requests\HealthReceipts;

use App\Http\Requests\CustomRequest;

class DeleteRequest extends CustomRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required'],
        ];
    }
}
