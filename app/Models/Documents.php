<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentsEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Documents extends Model
{
    use SoftDeletes;

    protected $table = 'documents';

    protected $fillable = [
        'user_id',
        'title',
        'type',
        'path',
        'active',
    ];

    protected $casts = [
        'type' => DocumentsEnum::class,
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function findById(int $id, int $userId, array $cols = ['*']): ?self
    {
        return $this->query()
            ->select($cols)
            ->where('id', $id)
            ->where('active', 1)
            ->where(function ($query) use ($userId) {
                return $query->where('user_id', $userId)
                    ->orWhereNull('user_id');
            })
            ->first();
    }
}
