<?php

declare(strict_types=1);

namespace App\Http\Requests\VideoRoomTranscriptions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SegmentRequest extends FormRequest
{
    // Cerca de 33 horas de trechos de 2 minutos: nenhuma sessão chega perto.
    private const int MAX_SEQUENCE = 999;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sequence' => ['required', 'integer', 'min:0', 'max:'.self::MAX_SEQUENCE],
            'mime' => ['required', 'string', Rule::in(array_keys((array) config('session_transcription.segment_mimes')))],
            'bytes' => ['required', 'integer', 'min:1', 'max:'.(int) config('session_transcription.segment_max_bytes')],
            'start_ms' => ['required', 'integer', 'min:0', 'max:86400000'],
            'duration_ms' => ['required', 'integer', 'min:1', 'max:'.((int) config('session_transcription.segment_max_seconds') * 1000)],
            'guest_count' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }
}
