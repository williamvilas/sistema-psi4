<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalRecords extends Model
{
    use SoftDeletes;

    // Res. CFP 001/2009: guarda mínima do registro documental.
    public const RETENTION_YEARS = 5;

    protected $table = 'medical_records';

    protected $fillable = [
        'agenda_id',
        'user_id',
        'patient_id',
        'title',
        'date_of_consultation',
        'notes_path',
    ];

    protected $casts = [
        'agenda_id' => 'integer',
        'user_id' => 'integer',
        'patient_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'date_of_consultation' => 'datetime',
    ];

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(Agenda::class, 'agenda_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patients::class, 'patient_id');
    }

    public function deleteInBach(array $id, int $userId, bool $force = false): int
    {
        $query = self::query()
            ->whereIn('id', $id)
            ->where('user_id', $userId);

        if ($force) {
            return $query->forceDelete();
        }

        return $query->delete();
    }

    public function isWithinRetention(): bool
    {
        return (bool) $this->date_of_consultation?->gt(now()->subYears(self::RETENTION_YEARS));
    }

    public function countWithinRetention(array $ids, int $userId): int
    {
        return self::query()
            ->whereIn('id', $ids)
            ->where('user_id', $userId)
            ->where('date_of_consultation', '>', now()->subYears(self::RETENTION_YEARS))
            ->count();
    }

    /**
     * Prontuários do paciente em ordem cronológica, para exportação.
     */
    public function listByPatient(int $patientId, int $userId, array $cols = ['*']): Collection
    {
        return self::query()
            ->select($cols)
            ->where('user_id', $userId)
            ->where('patient_id', $patientId)
            ->orderBy('date_of_consultation')
            ->orderBy('id')
            ->get();
    }

    public function findById(int $id, int $userId, array $cols = ['*']): ?self
    {
        return self::query()->select($cols)->where('id', $id)->where('user_id', $userId)->first();
    }

    public function findByIdWithPatient(int $id, int $userId, array $cols = ['*']): ?self
    {
        return self::query()->select($cols)->with('patient:id,name,phone')->where('id', $id)->where('user_id', $userId)->first();
    }

    public function paginateByUser(int $userId, int $limit, array $params): LengthAwarePaginator
    {
        return self::query()
            ->select([
                'medical_records.id',
                'medical_records.agenda_id',
                'medical_records.patient_id',
                'medical_records.title',
                'medical_records.date_of_consultation',
                'medical_records.updated_at',
                'patients.name as patient_name',
            ])
            ->join('patients', 'patients.id', '=', 'medical_records.patient_id')
            ->whereNull('patients.deleted_at')
            ->where('medical_records.user_id', $userId)
            ->when(! empty($params['q']), function ($query) use ($params) {
                $query->where('medical_records.title', 'like', '%'.self::escapeLike($params['q']).'%');
            })
            ->when(! empty($params['patient_id']), function ($query) use ($params) {
                $query->where('medical_records.patient_id', $params['patient_id']);
            })
            ->when(! empty($params['start_date']) && ! empty($params['end_date']), function ($query) use ($params) {
                $query->whereBetween('medical_records.date_of_consultation', [
                    $params['start_date'],
                    $params['end_date'],
                ]);
            })
            ->orderByDesc('medical_records.date_of_consultation')
            ->orderByDesc('medical_records.id')
            ->paginate($limit);
    }

    /**
     * Percorre em lotes (sem carregar tudo em memória) os prontuários apagados que
     * não pertencem a uma agenda, entregando apenas id e notes_path.
     */
    public function chunkDeletedWithoutAgenda(int $size, callable $callback): bool
    {
        return self::query()
            ->onlyTrashed()
            ->select(['id', 'notes_path'])
            ->whereNull('agenda_id')
            ->chunkById($size, $callback);
    }

    public function forceDeleteTrashedByIds(array $ids): int
    {
        return self::query()->onlyTrashed()->whereIn('id', $ids)->forceDelete();
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
