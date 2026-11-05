<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScaleEncryptionEnum;
use App\Enums\ScaleEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientScaleApplications extends Model
{
    use SoftDeletes;

    protected $table = 'patient_scale_applications';

    protected $fillable = [
        'user_id',
        'patient_id',
        'scale',
        'applied_on',
        'content_path',
        'encryption',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'patient_id' => 'integer',
        'scale' => ScaleEnum::class,
        'applied_on' => 'date',
        'encryption' => ScaleEncryptionEnum::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patients::class, 'patient_id');
    }

    public function isWithinRetention(): bool
    {
        return (bool) $this->applied_on?->gt(now()->subYears(MedicalRecords::RETENTION_YEARS));
    }

    /**
     * Aplicações do paciente em ordem cronológica (gráfico e exportação).
     */
    public function listByPatient(int $patientId, int $userId, array $cols = ['*']): Collection
    {
        return self::query()
            ->select($cols)
            ->where('user_id', $userId)
            ->where('patient_id', $patientId)
            ->orderBy('applied_on')
            ->orderBy('id')
            ->get();
    }

    public function findById(int $id, int $userId, array $cols = ['*']): ?self
    {
        return self::query()->select($cols)->where('id', $id)->where('user_id', $userId)->first();
    }

    // Delete em massa não dispara o observer: quem chama limpa o cache do dashboard.
    public function deleteById(int $id, int $userId): int
    {
        return self::query()->where('id', $id)->where('user_id', $userId)->delete();
    }

    /**
     * Data da aplicação mais recente (de qualquer escala) por paciente.
     *
     * @return \Illuminate\Support\Collection<int, string> patient_id => Y-m-d
     */
    public function lastAppliedOnByPatient(int $userId): \Illuminate\Support\Collection
    {
        return self::query()
            ->selectRaw('patient_id, MAX(applied_on) AS last_applied_on')
            ->where('user_id', $userId)
            ->groupBy('patient_id')
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->patient_id => (string) $row->last_applied_on]);
    }
}
