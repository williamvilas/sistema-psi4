<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

class DownloadRequest extends PreviewRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'id' => ['required'],
        ]);
    }
}
