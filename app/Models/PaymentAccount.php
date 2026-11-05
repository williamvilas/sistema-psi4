<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentAccountsStatusEnum;
use App\Enums\PaymentGatewayEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAccount extends Model
{
    protected $table = 'payment_accounts';

    protected $fillable = [
        'user_id',
        'provider',
        'public_key',
        'access_token',
        'refresh_token',
        'mp_user_id',
        'scope',
        'token_expires_at',
        'status',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'mp_user_id' => 'integer',
        'provider' => PaymentGatewayEnum::class,
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'status' => PaymentAccountsStatusEnum::class,
        'token_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function findIntegratedByUser(int $userId, array $cols = ['*']): ?self
    {
        return PaymentAccount::query()
            ->select($cols)
            ->where('user_id', $userId)
            ->where('status', PaymentAccountsStatusEnum::INTEGRATED->value)
            ->whereNotNull('access_token')
            ->first();
    }

    public function findByUserAndProvider(int $userId, string $provider, array $cols = ['*']): ?self
    {
        return PaymentAccount::query()
            ->select($cols)
            ->where('user_id', $userId)
            ->where('provider', $provider)
            ->first();
    }
}
