<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccessLogActionEnum;
use App\Enums\FeatureUsageEnum;
use App\Enums\PaginateCacheContextEnum;
use App\Helpers\FileHelper;
use App\Helpers\PaginateCache;
use App\Jobs\RegisterLog;
use App\Models\MedicalRecords;
use App\Models\Patients;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

readonly class MedicalRecordService
{
    // A exportação baixa vários arquivos em sequência; o link de 1 minuto da visualização não basta.
    private const EXPORT_LINK_MINUTES = 10;

    public function __construct(
        private MedicalRecords $medicalRecords,
        private Patients $patients,
        private EncryptionKeyService $encryptionKeyService,
        private AccessLogService $accessLogService,
        private PlanLimitService $planLimitService = new PlanLimitService,
    ) {
    }

    public function paginate(int $userId, int $limit, array $params): LengthAwarePaginator
    {
        return PaginateCache::remember(
            context: PaginateCacheContextEnum::MEDICAL_RECORDS,
            userId: $userId,
            params: [...$params, 'limit' => $limit],
            callback: fn () => $this->medicalRecords->paginateByUser(userId: $userId, limit: $limit, params: $params),
        );
    }

    public function store(array $data, int $userId): MedicalRecords
    {
        $this->assertPatientBelongsToUser(patientId: (int) $data['patient_id'], userId: $userId);
        $this->assertEncryptionKeyExists(userId: $userId);

        // O cache da listagem é invalidado pelo MedicalRecordsObserver.
        return DB::transaction(function () use ($data, $userId) {
            $this->planLimitService->consume(userId: $userId, feature: FeatureUsageEnum::MEDICAL_RECORDS);

            $record = $this->medicalRecords->create([
                'agenda_id' => $data['agenda_id'] ?? null,
                'user_id' => $userId,
                'patient_id' => $data['patient_id'],
                'title' => $data['title'],
                'notes_path' => $data['notes_path'],
                'date_of_consultation' => $data['date_of_consultation'],
            ]);

            $this->accessLogService->register(userId: $userId, action: AccessLogActionEnum::MEDICAL_RECORD_CREATED, medicalRecordId: $record->id);

            return $record;
        });
    }

    public function update(int $id, array $data, int $userId): MedicalRecords
    {
        $this->assertPatientBelongsToUser(patientId: (int) $data['patient_id'], userId: $userId);

        $oldNotesPath = null;

        $record = DB::transaction(function () use ($id, $data, $userId, &$oldNotesPath) {
            $medical = $this->medicalRecords->findById(id: $id, userId: $userId);

            if (! $medical) {
                throw new \DomainException(__('medical-records.not_found'));
            }

            $oldNotesPath = $medical->notes_path;

            $medical->patient_id = $data['patient_id'];
            $medical->title = $data['title'];
            $medical->date_of_consultation = $data['date_of_consultation'];
            $medical->notes_path = $data['notes_path'];

            if (! $medical->save()) {
                throw new \DomainException(__('errors.update_failed'));
            }

            $this->accessLogService->register(userId: $userId, action: AccessLogActionEnum::MEDICAL_RECORD_UPDATED, medicalRecordId: $medical->id);

            return $medical;
        });

        // O arquivo antigo só é removido após o commit, para não perdê-lo caso a transação falhe.
        if ($oldNotesPath && $oldNotesPath !== $record->notes_path) {
            FileHelper::remove($oldNotesPath);
        }

        return $record;
    }

    public function details(int $id, int $userId): ?MedicalRecords
    {
        $cols = [
            'id',
            'agenda_id',
            'user_id',
            'patient_id',
            'title',
            'date_of_consultation',
            'notes_path',
            'updated_at',
        ];

        return $this->medicalRecords->findByIdWithPatient(id: $id, userId: $userId, cols: $cols);
    }

    // O link temporário é o único caminho até o conteúdo: emiti-lo conta como acesso ao prontuário.
    public function getContentLink(MedicalRecords $record, int $userId): string
    {
        $this->accessLogService->register(userId: $userId, action: AccessLogActionEnum::MEDICAL_RECORD_VIEWED, medicalRecordId: $record->id);

        return FileHelper::getLinkTemp(path: $record->notes_path, minutes: 1);
    }

    public function delete(array $IDs, int $userId, bool $force = false, bool $retentionConfirmed = false): void
    {
        try {
            $ids = Arr::map($IDs, fn ($id) => decrypt($id));

            // Res. CFP 001/2009: dentro do prazo de guarda, o profissional precisa confirmar que está ciente.
            if (! $retentionConfirmed && $this->medicalRecords->countWithinRetention(ids: $ids, userId: $userId) > 0) {
                throw new \DomainException(__('medical-records.retention_confirmation_required'));
            }

            DB::transaction(function () use ($ids, $userId, $force) {
                $deleted = $this->medicalRecords->deleteInBach(id: $ids, userId: $userId, force: $force);

                if (! $deleted) {
                    throw new \Exception('failed_deleted_medical_record');
                }

                $this->accessLogService->registerMany(userId: $userId, action: AccessLogActionEnum::MEDICAL_RECORD_DELETED, medicalRecordIds: array_map('intval', $ids));
            });

            PaginateCache::forget($userId, PaginateCacheContextEnum::MEDICAL_RECORDS);

        } catch (\DomainException $d) {
            throw $d;
        } catch (\Throwable $t) {
            RegisterLog::dispatchSync([
                'data' => $IDs,
                'message' => $t->getMessage(),
                'method' => 'MedicalRecordService->delete',
            ]);

            throw new \DomainException(__('messages.failed_delete'));
        }
    }

    /**
     * Prontuários do paciente com links temporários do conteúdo (cifrado), para o navegador
     * decifrar e montar o documento de exportação. Cada prontuário exportado conta como acesso.
     */
    public function exportByPatient(int $patientId, int $userId): array
    {
        $patient = $this->patients->findById(id: $patientId, userId: $userId, cols: ['id', 'name']);

        if (! $patient) {
            throw new \DomainException(__('medical-records.patient_not_found'));
        }

        $records = $this->medicalRecords->listByPatient(patientId: $patientId, userId: $userId, cols: [
            'id', 'title', 'date_of_consultation', 'notes_path',
        ]);

        $this->accessLogService->registerMany(
            userId: $userId,
            action: AccessLogActionEnum::MEDICAL_RECORD_EXPORTED,
            medicalRecordIds: $records->pluck('id')->all(),
        );

        return [
            'patient' => $patient->name,
            'records' => $records->map(fn (MedicalRecords $record) => [
                'title' => $record->title,
                'date' => $record->date_of_consultation?->format('d/m/Y'),
                'notes_path' => $record->notes_path,
                'link' => FileHelper::getLinkTemp(path: $record->notes_path, minutes: self::EXPORT_LINK_MINUTES),
            ])->values()->all(),
        ];
    }

    private function assertEncryptionKeyExists(int $userId): void
    {
        if (! $this->encryptionKeyService->exists($userId)) {
            throw new \DomainException(__('encryption-key.required'));
        }
    }

    private function assertPatientBelongsToUser(int $patientId, int $userId): void
    {
        if (! $this->patients->findById(id: $patientId, userId: $userId, cols: ['id'])) {
            throw new \DomainException(__('medical-records.patient_not_found'));
        }
    }
}
