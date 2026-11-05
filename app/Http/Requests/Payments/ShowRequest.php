<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Http\Requests\CustomRequest;

class ShowRequest extends CustomRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required'],
        ];
    }
}
