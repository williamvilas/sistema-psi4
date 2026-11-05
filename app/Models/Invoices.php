<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoices extends Model
{
    use SoftDeletes;

    protected $table = 'invoices';

    protected $fillable = [
        'order_id',
        'status',
        'amount',
        'net_amount',
        'gateway_fee',
        'tax',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'deleted_at' => 'datetime',
    ];
}
