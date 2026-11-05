<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Logs extends Model
{
    protected $table = 'logs';

    protected $fillable = [
        'user_id',
        'message',
        'route',
        'method',
        'exception',
        'data',
        'sent',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'exception' => 'array',
        'data' => 'array',
        'sent' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getAllNotSentCursor()
    {
        return self::query()
            ->select(['id', 'user_id', 'message', 'route', 'method', 'sent'])
            ->where('sent', false)
            ->orderBy('id')
            ->cursor();
    }

    public function deleteOlderThan15Days(): int
    {
        return self::query()
            ->where('created_at', '<', now()->subDays(15))
            ->delete();
    }
}
