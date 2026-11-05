<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccessLogActionEnum;
use App\Enums\FeatureUsageEnum;
use App\Enums\ScaleEnum;
use App\Helpers\DashboardCache;
use App\Helpers\FileHelper;
use App\Models\Patients;
use App\Models\PatientScaleApplications;
use Illuminate\Support\Facades\DB;

readonly class ScaleApplicationService
{
    // O navegador baixa e decifra todas as aplicações em sequência para montar o gráfico.
    private const LINK_MINUTES = 10;

    public function __construct(
        private PatientScaleApplications $scaleApplications,
        private Patients $patients,
        private EncryptionKeyService $encryptionKeyService,
        private AccessLogService $accessLogService,
        private PlanLimitService $planLimitService = new PlanLimitService,
    ) {
    }

    /**
     * Definições das escalas disponíveis, para o navegador montar o questionário e calcular a pontuação.
     */
    public function catalog(): array
    {
        return array_map(fn (ScaleEnum $scale) => $scale->definition(), ScaleEnum::cases());
    }

    public function store(array $data, int $userId): PatientScaleApplications
    {
        $this->assertPatientBelongsToUser(patientId: (int) $data['patient_id'], userId: $userId);

        if (! $this->encryptionKeyService->exists($userId)) {
            throw new \DomainException(__('encryption-key.required'));
        }

        return DB::transaction(function () use ($data, $userId) {
            $this->planLimitService->consume(userId: $userId, feature: FeatureUsageEnum::SCALE_APPLICATIONS);

            $application = $this->scaleApplications->create([
                'user_id' => $userId,
                'patient_id' => $data['patient_id'],
                'scale' => $data['scale'],
                'applied_on' => $data['applied_on'],
                'content_path' => $data['content_path'],
            ]);

            $this->accessLogService->registerScaleApplications(userId: $userId, action: AccessLogActionEnum::SCALE_APPLICATION_CREATED, scaleApplicationIds: [$application->id]);

            return $application;
        });
    }

    /**
     * Aplicações do paciente com links temporários do conteúdo cifrado. Emitir os links conta como acesso.
     */
    public function listByPatient(int $patientId, int $userId, AccessLogActionEnum $action = AccessLogActionEnum::SCALE_APPLICATION_VIEWED): array
    {
        $this->assertPatientBelongsToUser(patientId: $patientId, userId: $userId);

        $applications = $this->scaleApplications->listByPatient(patientId: $patientId, userId: $userId, cols: [
            'id', 'scale', 'applied_on', 'content_path', 'encryption',
        ]);

        $this->accessLogService->registerScaleApplications(userId: $userId, action: $action, scaleApplicationIds: $applications->pluck('id')->all());

        return $applications->map(fn (PatientScaleApplications $application) => [
            'id' => encrypt($application->id),
            'scale' => $application->scale->value,
            'name' => $application->scale->label(),
            'applied_on' => $application->applied_on->format('d/m/Y'),
            'applied_on_iso' => $application->applied_on->toDateString(),
            'within_retention' => $application->isWithinRetention(),
            'content_path' => $application->content_path,
            'encryption' => $application->encryption->value,
            'link' => FileHelper::getLinkTemp(path: $application->content_path, minutes: self::LINK_MINUTES),
        ])->values()->all();
    }

    public function exportByPatient(int $patientId, int $userId): array
    {
        return $this->listByPatient(patientId: $patientId, userId: $userId, action: AccessLogActionEnum::SCALE_APPLICATION_EXPORTED);
    }

    public function delete(int $id, int $userId, bool $retentionConfirmed = false): void
    {
        $application = $this->scaleApplications->findById(id: $id, userId: $userId, cols: ['id', 'applied_on']);

        if (! $application) {
            throw new \DomainException(__('scales.not_found'));
        }

        // Res. CFP 001/2009: dentro do prazo de guarda, o profissional precisa confirmar que está ciente.
        if (! $retentionConfirmed && $application->isWithinRetention()) {
            throw new \DomainException(__('scales.retention_confirmation_required'));
        }

        DB::transaction(function () use ($id, $userId) {
            if (! $this->scaleApplications->deleteById(id: $id, userId: $userId)) {
                throw new \DomainException(__('messages.failed_delete'));
            }

            $this->accessLogService->registerScaleApplications(userId: $userId, action: AccessLogActionEnum::SCALE_APPLICATION_DELETED, scaleApplicationIds: [$id]);
        });

        DashboardCache::forget($userId);
    }

    private function assertPatientBelongsToUser(int $patientId, int $userId): void
    {
        if (! $this->patients->findById(id: $patientId, userId: $userId, cols: ['id'])) {
            throw new \DomainException(__('scales.patient_not_found'));
        }
    }
}
