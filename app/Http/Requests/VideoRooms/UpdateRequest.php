<?php

declare(strict_types=1);

namespace App\Http\Requests\VideoRooms;

use App\Models\VideoRoom;
use Carbon\Carbon;
use Illuminate\Validation\Validator;

class UpdateRequest extends StoreRequest
{
    public function rules(): array
    {
        $participantsRules = $this->participantsRules();
        $participantsRules['participants'][0] = 'sometimes';

        return [
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date'],
            ...$participantsRules,
        ];
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            fn (Validator $validator) => $this->validatePeriod($validator),
        ];
    }

    /**
     * Quando só um dos horários é enviado, compara com o valor atual da sala.
     */
    private function validatePeriod(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['starts_at', 'ends_at']) || ! $this->hasAny(['starts_at', 'ends_at'])) {
            return;
        }

        $videoRoom = VideoRoom::query()->where('room', (string) $this->route('room'))->first();

        if ($videoRoom === null && ! $this->has(['starts_at', 'ends_at'])) {
            return;
        }

        $startsAt = $this->has('starts_at') ? Carbon::parse($this->input('starts_at')) : $videoRoom->starts_at;
        $endsAt = $this->has('ends_at') ? Carbon::parse($this->input('ends_at')) : $videoRoom->ends_at;

        if ($endsAt->lte($startsAt)) {
            $validator->errors()->add('ends_at', __('video-room.ends_after_starts'));
        }
    }
}
