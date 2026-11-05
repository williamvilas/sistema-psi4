<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FunnelTagEnum;
use Illuminate\Database\Eloquent\Model;

class FunnelEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'funnel_events';

    protected $fillable = [
        'visitor_id',
        'tag',
        'user_id',
        'path',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'tag' => FunnelTagEnum::class,
        'created_at' => 'datetime',
    ];

    /**
     * Grava o evento ignorando duplicidade de (visitor_id, tag).
     */
    public function storeIgnoringDuplicate(array $data): bool
    {
        $data['created_at'] = now();

        return static::query()->insertOrIgnore($data) > 0;
    }

    public function deleteOlderThan(\DateTimeInterface $date): int
    {
        return static::query()->where('created_at', '<', $date)->delete();
    }
}
