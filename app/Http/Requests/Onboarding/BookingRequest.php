<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Http\Requests\BookingSettings\StoreRequest;

class BookingRequest extends StoreRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'services' => ['required', 'array', 'min:1'],
        ]);
    }

    public function toPayload(): array
    {
        return ['public_booking_enabled' => true] + parent::toPayload();
    }
}
