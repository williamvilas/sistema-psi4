<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SessionActivityEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionActivities extends Model
{
    protected $table = 'session_activities';

    protected $fillable = [
        'uuid',
        'user_id',
        'patient_id',
        'agenda_id',
        'medical_record_id',
        'activity',
        'occurred_on',
        'content_path',
    ];

    protected $casts = [
        'activity' => SessionActivityEnum::class,
        'occurred_on' => 'date',
    ];

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecords::class, 'medical_record_id');
    }

    public function findByUuid(string $uuid): ?self
    {
        return $this->newQuery()->where('uuid', $uuid)->first();
    }

    /**
     * Registros de um paciente com o prontuário ainda não apagado, do mais antigo para o mais novo.
     *
     * @return Collection<int, self>
     */
    public function listByPatient(int $userId, int $patientId, SessionActivityEnum $activity): Collection
    {
        return $this->newQuery()
            ->where('user_id', $userId)
            ->where('patient_id', $patientId)
            ->where('activity', $activity)
            ->whereHas('medicalRecord')
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get(['id', 'uuid', 'medical_record_id', 'occurred_on', 'content_path']);
    }
}
