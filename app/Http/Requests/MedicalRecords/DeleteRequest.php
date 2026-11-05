<?php

declare(strict_types=1);

namespace App\Http\Requests\MedicalRecords;

use App\Http\Requests\CustomRequest;

class DeleteRequest extends CustomRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['required', 'string'],
            'retention_confirmed' => ['nullable', 'boolean'],
        ];
    }

    public function getIDs(): array
    {
        return $this->input('ids');
    }

    public function getRetentionConfirmed(): bool
    {
        return $this->boolean('retention_confirmed');
    }

    public function getForce(): bool
    {
        $force = $this->input('force');

        if ($force) {
            return true;
        }

        return false;
    }
}
