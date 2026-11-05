<?php

declare(strict_types=1);

namespace App\Http\Requests\VideoRoomAccess;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room' => ['required', 'string', 'max:32'],
            'cpf' => ['required_without:phone', 'nullable', 'string', 'max:40'],
            'phone' => ['required_without:cpf', 'nullable', 'string', 'max:40'],
        ];
    }

    /**
     * O front do Jitsi exibe só `message`: devolve um texto fixo e sem ecoar os campos enviados.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['message' => __('video-room.access_required')], 422));
    }
}
