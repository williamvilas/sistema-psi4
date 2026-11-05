<?php

declare(strict_types=1);

namespace App\Http\Requests\VideoRoomTranscriptions;

use App\Enums\SessionTranscriptionConsentEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', new Enum(SessionTranscriptionConsentEnum::class)],
        ];
    }
}
