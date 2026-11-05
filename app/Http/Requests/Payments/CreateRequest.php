<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Helpers\StringHelper;
use Illuminate\Foundation\Http\FormRequest;

class CreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient' => ['nullable', 'string'],
        ];
    }

    /**
     * Paciente pré-selecionado via `?patient=<id criptografado>` (atalho "Cobrar" da ficha do paciente).
     */
    public function getPatientId(): ?int
    {
        $id = StringHelper::decrypt($this->query('patient'));

        return $id ? (int) $id : null;
    }
}
