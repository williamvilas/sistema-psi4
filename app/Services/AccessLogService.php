<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccessLogActionEnum;
use App\Models\AccessLogs;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class AccessLogService
{
    public function __construct(private readonly AccessLogs $accessLogs)
    {
    }

    public function register(int $userId, AccessLogActionEnum $action, ?int $medicalRecordId = null): void
    {
        $this->registerMany(userId: $userId, action: $action, medicalRecordIds: [$medicalRecordId]);
    }

    /**
     * Um registro por prontuário (ex.: exclusão em lote); [null] registra uma ação sem prontuário.
     */
    public function registerMany(int $userId, AccessLogActionEnum $action, array $medicalRecordIds): void
    {
        if (empty($medicalRecordIds)) {
            return;
        }

        $ip = request()->ip();
        $userAgent = Str::limit((string) request()->userAgent(), 252) ?: null;
        $now = now();

        $rows = array_map(fn ($id) => [
            'user_id' => $userId,
            'action' => $action->value,
            'medical_record_id' => $id,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'created_at' => $now,
        ], array_values($medicalRecordIds));

        $this->accessLogs->insertMany($rows);
    }

    /**
     * Um registro por aplicação de escala (ex.: abrir os resultados de todas as escalas do paciente).
     */
    public function registerScaleApplications(int $userId, AccessLogActionEnum $action, array $scaleApplicationIds): void
    {
        if (empty($scaleApplicationIds)) {
            return;
        }

        $ip = request()->ip();
        $userAgent = Str::limit((string) request()->userAgent(), 252) ?: null;
        $now = now();

        $rows = array_map(fn ($id) => [
            'user_id' => $userId,
            'action' => $action->value,
            'scale_application_id' => $id,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'created_at' => $now,
        ], array_values($scaleApplicationIds));

        $this->accessLogs->insertMany($rows);
    }

    public function paginate(int $userId, int $limit, array $params): LengthAwarePaginator
    {
        return $this->accessLogs->paginateByUser(userId: $userId, limit: $limit, params: $params);
    }
}
