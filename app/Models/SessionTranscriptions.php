<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SessionTranscriptionStatusEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionTranscriptions extends Model
{
    protected $table = 'session_transcriptions';

    protected $fillable = [
        'uuid',
        'user_id',
        'patient_id',
        'agenda_id',
        'video_room_id',
        'medical_record_id',
        'status',
        'parts_count',
        'duration_seconds',
        'occurred_on',
        'adult_confirmed_at',
        'started_at',
        'ended_at',
        'reviewed_at',
        'review_deadline',
    ];

    protected $casts = [
        'status' => SessionTranscriptionStatusEnum::class,
        'parts_count' => 'integer',
        'duration_seconds' => 'integer',
        'occurred_on' => 'date',
        'adult_confirmed_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'review_deadline' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patients::class, 'patient_id');
    }

    public function videoRoom(): BelongsTo
    {
        return $this->belongsTo(VideoRoom::class, 'video_room_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(SessionTranscriptionConsents::class, 'session_transcription_id');
    }

    public function findByUuid(string $uuid): ?self
    {
        return $this->newQuery()->where('uuid', $uuid)->first();
    }

    public function findInRoom(string $uuid, int $videoRoomId): ?self
    {
        return $this->newQuery()->where('uuid', $uuid)->where('video_room_id', $videoRoomId)->first();
    }

    public function findPendingForUser(string $uuid, int $userId, bool $lock = false): ?self
    {
        return $this->newQuery()
            ->where('uuid', $uuid)
            ->where('user_id', $userId)
            ->where('status', SessionTranscriptionStatusEnum::PENDING_REVIEW)
            ->with('patient:id,name')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    public function activeInRoom(int $videoRoomId): ?self
    {
        return $this->newQuery()
            ->where('video_room_id', $videoRoomId)
            ->whereIn('status', [SessionTranscriptionStatusEnum::AWAITING_CONSENT, SessionTranscriptionStatusEnum::RECORDING])
            ->first();
    }

    public function paginatePending(int $userId, int $limit): LengthAwarePaginator
    {
        return $this->newQuery()
            ->select(['id', 'uuid', 'patient_id', 'occurred_on', 'duration_seconds', 'review_deadline'])
            ->with('patient:id,name')
            ->where('user_id', $userId)
            ->where('status', SessionTranscriptionStatusEnum::PENDING_REVIEW)
            ->whereHas('patient')
            ->orderBy('review_deadline')
            ->paginate($limit);
    }

    /**
     * Tudo o que foi pedido, para a prova do consentimento. Só depois que alguém decidiu ou que a captura começou: um pedido
     * sem resposta ainda em aberto não prova nada.
     */
    public function paginateHistory(int $userId, int $limit): LengthAwarePaginator
    {
        return $this->newQuery()
            ->select(['id', 'uuid', 'patient_id', 'occurred_on', 'status'])
            ->with(['patient' => fn ($patient) => $patient->withTrashed()->select(['id', 'name'])])
            ->where('user_id', $userId)
            ->where('status', '!=', SessionTranscriptionStatusEnum::AWAITING_CONSENT)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate($limit);
    }

    public function findWithConsents(string $uuid, int $userId): ?self
    {
        return $this->newQuery()
            ->with([
                'patient' => fn ($patient) => $patient->withTrashed()->select(['id', 'name']),
                'consents' => fn ($consents) => $consents->orderBy('decided_at'),
            ])
            ->where('uuid', $uuid)
            ->where('user_id', $userId)
            ->first();
    }

    public function countPendingByPatient(int $patientId, int $userId): int
    {
        return $this->newQuery()
            ->where('user_id', $userId)
            ->where('patient_id', $patientId)
            ->where('status', SessionTranscriptionStatusEnum::PENDING_REVIEW)
            ->count();
    }

    public function countPending(int $userId): int
    {
        return $this->newQuery()
            ->where('user_id', $userId)
            ->where('status', SessionTranscriptionStatusEnum::PENDING_REVIEW)
            ->whereHas('patient')
            ->count();
    }

    /**
     * @return Collection<int, self>
     */
    public function listByPatients(array $patientIds): Collection
    {
        return $this->newQuery()->whereIn('patient_id', $patientIds)->get(['id', 'uuid', 'parts_count']);
    }
}
