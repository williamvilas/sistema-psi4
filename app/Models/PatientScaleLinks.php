<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScaleEnum;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientScaleLinks extends Model
{
    use SoftDeletes;

    // Prazo para o paciente responder; depois disso (ou respondido) o link deixa de funcionar.
    public const VALID_DAYS = 7;

    protected $table = 'patient_scale_links';

    protected $fillable = [
        'user_id',
        'patient_id',
        'scale',
        'token_hash',
        'token',
        'content_path',
        'expires_at',
        'completed_at',
        'revoked_at',
        'scale_application_id',
    ];

    protected $hidden = [
        'token_hash',
        'token',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'patient_id' => 'integer',
        'scale' => ScaleEnum::class,
        'token' => 'encrypted',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'scale_application_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patients::class, 'patient_id');
    }

    public function isPending(): bool
    {
        return ! $this->completed_at && ! $this->revoked_at && $this->expires_at?->isFuture();
    }

    public function status(): string
    {
        return match (true) {
            (bool) $this->completed_at => 'completed',
            (bool) $this->revoked_at => 'revoked',
            ! $this->expires_at?->isFuture() => 'expired',
            default => 'pending',
        };
    }

    public function findByTokenHash(string $tokenHash, array $cols = ['*'], bool $lock = false): ?self
    {
        return self::query()
            ->select($cols)
            ->where('token_hash', $tokenHash)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    public function findById(int $id, int $userId, array $cols = ['*']): ?self
    {
        return self::query()->select($cols)->where('id', $id)->where('user_id', $userId)->first();
    }

    /**
     * Link do dia (não cancelado) para o mesmo paciente e escala: no máximo um por dia.
     * $since: início do dia no fuso da psicóloga, em UTC.
     */
    public function findTodayByPatientAndScale(int $userId, int $patientId, string $scale, CarbonInterface $since, array $cols = ['*']): ?self
    {
        return self::query()
            ->select($cols)
            ->where('user_id', $userId)
            ->where('patient_id', $patientId)
            ->where('scale', $scale)
            ->whereNull('revoked_at')
            ->where('created_at', '>=', $since)
            ->orderByDesc('id')
            ->first();
    }

    public function paginateByPatient(int $userId, int $patientId, int $limit, array $cols = ['*']): LengthAwarePaginator
    {
        return self::query()
            ->select($cols)
            ->where('user_id', $userId)
            ->where('patient_id', $patientId)
            ->orderByDesc('id')
            ->paginate($limit);
    }
}
