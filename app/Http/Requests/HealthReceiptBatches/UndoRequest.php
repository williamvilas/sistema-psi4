<?php

declare(strict_types=1);

namespace App\Http\Requests\HealthReceiptBatches;

use App\Http\Requests\CustomRequest;

class UndoRequest extends CustomRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required'],
        ];
    }
}
