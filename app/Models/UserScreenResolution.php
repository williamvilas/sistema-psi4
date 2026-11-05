<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserScreenResolution extends Model
{
    protected $table = 'user_screen_resolutions';

    protected $fillable = [
        'user_id',
        'width',
        'height',
        'captured_date',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'captured_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function existsByUser(int $width, int $height, int $userId, array $cols = ['*']): ?self
    {
        return $this->query()
            ->select($cols)
            ->where('width', $width)
            ->where('height', $height)
            ->where('user_id', $userId)
            ->first();
    }
}
