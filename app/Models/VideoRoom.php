<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VideoRoomStatusEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoRoom extends Model
{
    protected $table = 'video_rooms';

    protected $fillable = [
        'room',
        'session_id',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status' => VideoRoomStatusEnum::class,
    ];

    public function participants(): HasMany
    {
        return $this->hasMany(VideoRoomParticipant::class, 'video_room_id')->orderBy('id');
    }

    /**
     * Salas ativas das sessões informadas (session_id = id da consulta na agenda).
     *
     * @param  array<int, int|string>  $sessionIds
     * @return Collection<int, self>
     */
    public function activeBySessions(array $sessionIds): Collection
    {
        if ($sessionIds === []) {
            return new Collection;
        }

        return $this->newQuery()
            ->whereIn('session_id', array_map('strval', $sessionIds))
            ->where('status', VideoRoomStatusEnum::ACTIVE)
            ->get();
    }
}
