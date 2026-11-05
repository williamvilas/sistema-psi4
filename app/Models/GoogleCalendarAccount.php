<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GoogleCalendarStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleCalendarAccount extends Model
{
    protected $table = 'google_calendar_accounts';

    protected $fillable = [
        'user_id',
        'google_user_id',
        'email',
        'access_token',
        'refresh_token',
        'scope',
        'token_expires_at',
        'calendar_id',
        'invite_patient',
        'block_busy',
        'status',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'invite_patient' => 'boolean',
        'block_busy' => 'boolean',
        'status' => GoogleCalendarStatusEnum::class,
        'token_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function findByUser(int $userId, array $cols = ['*']): ?self
    {
        return $this->newQuery()
            ->select($cols)
            ->where('user_id', $userId)
            ->first();
    }

    public function findIntegratedByUser(int $userId, array $cols = ['*']): ?self
    {
        return $this->newQuery()
            ->select($cols)
            ->where('user_id', $userId)
            ->where('status', GoogleCalendarStatusEnum::INTEGRATED->value)
            ->whereNotNull('refresh_token')
            ->first();
    }

    public function isIntegratedByUser(int $userId): bool
    {
        return $this->newQuery()
            ->where('user_id', $userId)
            ->where('status', GoogleCalendarStatusEnum::INTEGRATED->value)
            ->whereNotNull('refresh_token')
            ->exists();
    }

    /**
     * Conta conectada que pediu para bloquear os horários ocupados do Google.
     */
    public function findBusyBlockingByUser(int $userId, array $cols = ['*']): ?self
    {
        return $this->newQuery()
            ->select($cols)
            ->where('user_id', $userId)
            ->where('status', GoogleCalendarStatusEnum::INTEGRATED->value)
            ->where('block_busy', true)
            ->whereNotNull('refresh_token')
            ->first();
    }

    /**
     * O psicólogo pode ter recusado o escopo de livre/ocupado na tela de consentimento.
     */
    public function canReadFreeBusy(): bool
    {
        return in_array(config('google-calendar.freebusy_scope'), explode(' ', (string) $this->scope), true);
    }
}
