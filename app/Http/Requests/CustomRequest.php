<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Helpers\StringHelper;
use Illuminate\Foundation\Http\FormRequest;

class CustomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        return array_merge($this->all(), $this->route()->parameters());
    }

    public function getId(): ?int
    {
        $id = StringHelper::decrypt($this->route('id'));

        if ($id) {
            return (int) $id;
        }

        return null;
    }
}
