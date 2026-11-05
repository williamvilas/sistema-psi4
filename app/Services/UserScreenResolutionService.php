<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserScreenResolution;

readonly class UserScreenResolutionService
{
    public function __construct(private UserScreenResolution $screenResolution)
    {
    }

    public function store(array $data, ?int $userId = null): void
    {
        $existsScreen = $this->existsScreenByUser(
            width: (int) $data['width'],
            height: (int) $data['height'],
            userId: $userId
        );

        if ($existsScreen) {
            return;
        }

        $this->screenResolution->create([
            'user_id' => $userId,
            'width' => $data['width'],
            'height' => $data['height'],
            'captured_date' => now()->format('Y-m-d'),
        ]);
    }

    private function existsScreenByUser(int $width, int $height, ?int $userId = null): bool
    {
        if (! $userId) {
            return false;
        }

        $cols = ['id'];
        $exists = $this->screenResolution->existsByUser(width: $width, height: $height, userId: $userId, cols: $cols);

        if ($exists) {
            return true;
        }

        return false;
    }
}
