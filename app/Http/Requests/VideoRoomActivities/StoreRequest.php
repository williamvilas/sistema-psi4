<?php

declare(strict_types=1);

namespace App\Http\Requests\VideoRoomActivities;

use App\Enums\SessionActivityEnum;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreRequest extends FormRequest
{
    // 64 KB de arquivo cifrado, em base64.
    private const int MAX_CONTENT_LENGTH = 87384;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid' => ['required', 'string', 'regex:/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/'],
            'activity' => ['required', new Enum(SessionActivityEnum::class)],
            'grant' => ['required', 'string', 'max:1024'],
            'content' => ['required', 'string', 'max:'.self::MAX_CONTENT_LENGTH, function (string $attribute, mixed $value, Closure $fail) {
                // Barra texto claro enviado por engano: só o formato cifrado entra.
                $bytes = base64_decode((string) $value, true);

                if ($bytes === false || ! str_starts_with($bytes, 'PSA')) {
                    $fail(__('session-activities.cannot_save'));
                }
            }],
        ];
    }
}
