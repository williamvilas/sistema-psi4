<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'user_id',
        'feature',
        'key',
        'value',
    ];

    protected $casts = [
        'user_id' => 'integer',
    ];

    public function isActive(): bool
    {
        if ($this->value === '0') {
            return false;
        }

        return true;
    }
}
