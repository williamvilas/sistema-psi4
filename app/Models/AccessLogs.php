<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccessLogActionEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLogs extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'access_logs';

    protected $fillable = [
        'user_id',
        'action',
        'medical_record_id',
        'scale_application_id',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'medical_record_id' => 'integer',
        'scale_application_id' => 'integer',
        'action' => AccessLogActionEnum::class,
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function insertMany(array $rows): bool
    {
        return self::query()->insert($rows);
    }

    /**
     * Junta prontuário e paciente direto pelas tabelas (inclui os apagados): o registro de acesso
     * continua mostrando de qual prontuário se tratava mesmo depois da exclusão. O mesmo vale para as escalas.
     */
    public function paginateByUser(int $userId, int $limit, array $params): LengthAwarePaginator
    {
        return self::query()
            ->select([
                'access_logs.id',
                'access_logs.action',
                'access_logs.medical_record_id',
                'access_logs.ip',
                'access_logs.user_agent',
                'access_logs.created_at',
                'medical_records.title as medical_record_title',
                'medical_records.deleted_at as medical_record_deleted_at',
                'patient_scale_applications.scale as scale',
                'patient_scale_applications.deleted_at as scale_application_deleted_at',
                'users.name as user_name',
            ])
            ->selectRaw('COALESCE(patients.name, scale_patients.name) as patient_name')
            ->leftJoin('users', 'users.id', '=', 'access_logs.user_id')
            ->leftJoin('medical_records', 'medical_records.id', '=', 'access_logs.medical_record_id')
            ->leftJoin('patients', 'patients.id', '=', 'medical_records.patient_id')
            ->leftJoin('patient_scale_applications', 'patient_scale_applications.id', '=', 'access_logs.scale_application_id')
            ->leftJoin('patients as scale_patients', 'scale_patients.id', '=', 'patient_scale_applications.patient_id')
            ->where('access_logs.user_id', $userId)
            ->when(! empty($params['action']), function ($query) use ($params) {
                $query->where('access_logs.action', $params['action']);
            })
            ->when(isset($params['medical_record_id']), function ($query) use ($params) {
                $query->where('access_logs.medical_record_id', $params['medical_record_id']);
            })
            ->when(! empty($params['start_date']) && ! empty($params['end_date']), function ($query) use ($params) {
                $query->whereBetween('access_logs.created_at', [$params['start_date'], $params['end_date']]);
            })
            ->orderByDesc('access_logs.created_at')
            ->orderByDesc('access_logs.id')
            ->paginate($limit);
    }
}
