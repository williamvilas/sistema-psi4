<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VideoRoom;

/**
 * Id curto e público da sala de vídeo. Só minúsculas e dígitos: o Jitsi converte o nome da sala para minúsculas.
 */
class RoomIdGenerator
{
    private const string ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

    private const int LENGTH = 12;

    public function generate(): string
    {
        do {
            $room = $this->random();
        } while (VideoRoom::query()->where('room', $room)->exists());

        return $room;
    }

    private function random(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $room = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $room .= self::ALPHABET[random_int(0, $max)];
        }

        return $room;
    }
}
