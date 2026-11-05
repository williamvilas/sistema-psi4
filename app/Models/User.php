<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\PasswordResetMail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (self $user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }

            if (empty($user->slug)) {
                $user->slug = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'two_factor',
        'google_id',
        'linkedin_id',
        'name',
        'document',
        'whatsapp',
        'crp',
        'email',
        'password',
        'timezone',
        'waiting_list',
        'last_login',
        'current_plan',
        'code',
        'slug',
        'force_change_password',
        'temporary_password_expiration',
        'plan_expiration_date',
        'plan_activated_on',
        'photo',
        'terms_accepted_at',
        'terms_version',
        'onboarding_completed_at',
        'google_calendar_hero_dismissed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'session_activity_pin',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'google_calendar_hero_dismissed_at' => 'datetime',
        ];
    }

    public function sendPasswordResetNotification($token): void
    {
        $url = route('password.reset', ['token' => $token, 'email' => $this->email]);
        $expires = (int) config('auth.passwords.users.expire');

        Mail::to($this->email)->send(new PasswordResetMail($this->name, $url, $expires));
    }

    public function findBySlug(string $slug, array $cols = ['*']): ?self
    {
        return self::query()->select($cols)->where('slug', $slug)->first();
    }

    public function findBySocialId(string $column, string $socialId): ?self
    {
        return self::query()->where($column, $socialId)->first();
    }

    public function findByEmail(string $email): ?self
    {
        return self::query()->where('email', $email)->first();
    }

    public function slugExists(string $slug, ?int $exceptUserId = null): bool
    {
        return self::query()
            ->where('slug', $slug)
            ->when($exceptUserId, fn ($query) => $query->where('id', '!=', $exceptUserId))
            ->exists();
    }

    public function updatePhotoById(int $userId, ?string $photo): void
    {
        self::query()->where('id', $userId)->update(['photo' => $photo]);
    }

    public function hasCompletedOnboarding(): bool
    {
        return ! empty($this->onboarding_completed_at);
    }

    public function markOnboardingCompleted(): void
    {
        $this->forceFill(['onboarding_completed_at' => now()])->save();
    }

    public function hasDismissedGoogleCalendarHero(): bool
    {
        return ! empty($this->google_calendar_hero_dismissed_at);
    }

    public function dismissGoogleCalendarHero(): void
    {
        if ($this->hasDismissedGoogleCalendarHero()) {
            return;
        }

        $this->forceFill(['google_calendar_hero_dismissed_at' => now()])->save();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! empty($this->two_factor) && ! empty($this->two_factor_secret);
    }

    public function savePendingTwoFactorSecret(string $secret): void
    {
        $this->forceFill([
            'two_factor' => 0,
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
        ])->save();
    }

    public function enableTwoFactor(array $hashedRecoveryCodes, int $step): void
    {
        $this->forceFill([
            'two_factor' => 1,
            'two_factor_recovery_codes' => $hashedRecoveryCodes,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_step' => $step,
        ])->save();
    }

    public function disableTwoFactor(): void
    {
        $this->forceFill([
            'two_factor' => 0,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_step' => null,
        ])->save();
    }

    public function updateTwoFactorRecoveryCodes(array $hashedRecoveryCodes): void
    {
        $this->forceFill(['two_factor_recovery_codes' => $hashedRecoveryCodes])->save();
    }

    public function updateTwoFactorLastUsedStep(int $step): void
    {
        $this->forceFill(['two_factor_last_used_step' => $step])->save();
    }

    public function findById(int $id, array $cols = ['*']): ?self
    {
        return self::query()->find($id, $cols);
    }

    public function findCurrentPlanById(int $id): ?string
    {
        return self::query()->whereKey($id)->value('current_plan');
    }

    public function findTimezoneById(int $id): ?string
    {
        return self::query()->whereKey($id)->value('timezone');
    }

    public function getCode(): string
    {
        return $this->code;
    }
}
