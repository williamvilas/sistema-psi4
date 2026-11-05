<?php

declare(strict_types=1);

namespace App\Http\Requests\ScaleApplications;

use App\Helpers\StringHelper;
use Illuminate\Foundation\Http\FormRequest;

class DeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'string'],
            'retention_confirmed' => ['nullable', 'boolean'],
        ];
    }

    public function getId(): int
    {
        return (int) StringHelper::decrypt($this->input('id'));
    }

    public function getRetentionConfirmed(): bool
    {
        return $this->boolean('retention_confirmed');
    }
}
