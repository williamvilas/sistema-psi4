<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VideoRoomRoleEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoRoomParticipant extends Model
{
    protected $table = 'video_room_participants';

    protected $fillable = [
        'video_room_id',
        'cpf_hmac',
        'phone_hmac',
        'name',
        'role',
    ];

    protected $hidden = [
        'cpf_hmac',
        'phone_hmac',
    ];

    protected $casts = [
        'video_room_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'role' => VideoRoomRoleEnum::class,
    ];

    public function videoRoom(): BelongsTo
    {
        return $this->belongsTo(VideoRoom::class, 'video_room_id');
    }
}
