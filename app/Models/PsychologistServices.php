<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PsychologistServices extends Model
{
    use SoftDeletes;

    protected $table = 'psychologist_services';

    protected $fillable = [
        'user_id',
        'name',
        'price',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'price' => 'decimal:2',
    ];

    public function listByUser(int $userId, array $cols = ['id', 'name', 'price']): Collection
    {
        return self::query()->select($cols)->where('user_id', $userId)->orderBy('id')->get();
    }

    public function findByUser(int $id, int $userId, array $cols = ['id', 'name', 'price']): ?self
    {
        return self::query()->select($cols)->where('id', $id)->where('user_id', $userId)->first();
    }

    public function updateByUser(int $id, int $userId, array $data): int
    {
        return self::query()->where('id', $id)->where('user_id', $userId)->update($data);
    }

    public function deleteExcept(int $userId, array $keepIds): int
    {
        return self::query()->where('user_id', $userId)->whereNotIn('id', $keepIds)->delete();
    }
}
