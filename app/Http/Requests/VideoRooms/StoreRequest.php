<?php

declare(strict_types=1);

namespace App\Http\Requests\VideoRooms;

use App\Enums\VideoRoomRoleEnum;
use App\Services\CpfHasher;
use App\Services\PhoneHasher;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            ...$this->participantsRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'ends_at.after' => __('video-room.ends_after_starts'),
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateParticipants($validator)];
    }

    protected function participantsRules(): array
    {
        $cpfHasher = app(CpfHasher::class);
        $phoneHasher = app(PhoneHasher::class);

        return [
            'participants' => ['required', 'array', 'min:1', 'max:10'],
            'participants.*.cpf' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail) use ($cpfHasher) {
                if (! is_string($value) || $cpfHasher->normalize($value) === null) {
                    $fail(__('video-room.invalid_cpf'));
                }
            }],
            'participants.*.phone' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail) use ($phoneHasher) {
                if (! is_string($value) || $phoneHasher->normalize($value) === null) {
                    $fail(__('video-room.invalid_phone'));
                }
            }],
            'participants.*.name' => ['required', 'string', 'max:120'],
            'participants.*.role' => ['required', Rule::enum(VideoRoomRoleEnum::class)],
        ];
    }

    /**
     * Pelo menos um moderador, moderador sempre com CPF, convidado com CPF ou telefone e nenhum dado repetido
     * (comparando já normalizado).
     */
    protected function validateParticipants(Validator $validator): void
    {
        $participants = $this->input('participants');

        if (! is_array($participants) || $participants === []) {
            return;
        }

        $cpfHasher = app(CpfHasher::class);
        $phoneHasher = app(PhoneHasher::class);
        $seenCpfs = [];
        $seenPhones = [];
        $hasModerator = false;

        foreach ($participants as $index => $participant) {
            if (! is_array($participant)) {
                continue;
            }

            $isModerator = ($participant['role'] ?? null) === VideoRoomRoleEnum::MODERATOR->value;
            $hasModerator = $hasModerator || $isModerator;

            $cpf = is_string($participant['cpf'] ?? null) ? $cpfHasher->normalize($participant['cpf']) : null;
            $e164 = is_string($participant['phone'] ?? null) ? $phoneHasher->normalize($participant['phone']) : null;

            if (empty($participant['cpf']) && ($isModerator || empty($participant['phone']))) {
                $validator->errors()->add("participants.$index.cpf", __($isModerator ? 'video-room.moderator_cpf_required' : 'video-room.access_required'));
            }

            if ($cpf !== null && isset($seenCpfs[$cpf])) {
                $validator->errors()->add("participants.$index.cpf", __('video-room.duplicated_cpf'));
            }

            // O telefone só vale para quem não tem CPF
            if ($cpf === null && $e164 !== null && isset($seenPhones[$e164])) {
                $validator->errors()->add("participants.$index.phone", __('video-room.duplicated_phone'));
            }

            if ($cpf !== null) {
                $seenCpfs[$cpf] = true;
            } elseif ($e164 !== null) {
                $seenPhones[$e164] = true;
            }
        }

        if (! $hasModerator) {
            $validator->errors()->add('participants', __('video-room.moderator_required'));
        }
    }
}
