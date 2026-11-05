<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingSettings extends Model
{
    protected $table = 'booking_settings';

    protected $fillable = [
        'user_id',
        'public_booking_enabled',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'public_booking_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function findByUser(int $userId, array $cols = ['*']): ?self
    {
        return self::query()->select($cols)->where('user_id', $userId)->first();
    }

    public function saveByUser(int $userId, array $data): self
    {
        return self::query()->updateOrCreate(['user_id' => $userId], $data);
    }
}
