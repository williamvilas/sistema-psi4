<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgendaHolidays extends Model
{
    protected $table = 'agenda_holidays';

    protected $fillable = [
        'user_id',
        'date',
        'name',
        'work_enabled',
        'start',
        'end',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'work_enabled' => 'boolean',
    ];

    /**
     * Substitui os feriados de hoje em diante; os que já passaram são preservados.
     */
    public function replaceByUser(int $userId, array $holidays): void
    {
        self::query()
            ->where('user_id', $userId)
            ->whereDate('date', '>=', today())
            ->delete();

        foreach ($holidays as $holiday) {
            self::query()->updateOrCreate(
                ['user_id' => $userId, 'date' => $holiday['date']],
                $holiday,
            );
        }
    }
}
