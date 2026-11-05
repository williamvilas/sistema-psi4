<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccessLogActionEnum;
use App\Enums\FeatureUsageEnum;
use App\Enums\ScaleEncryptionEnum;
use App\Exceptions\ScaleLinkUnavailableException;
use App\Models\Patients;
use App\Models\PatientScaleApplications;
use App\Models\PatientScaleLinks;
use App\Models\User;
use App\Models\UserEncryptionKeys;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Links para o paciente responder uma escala sem login.
 * As respostas são cifradas no navegador do paciente com a chave pública das escalas da psicóloga:
 * o servidor só conhece a escala, a data e se o link foi respondido.
 */
readonly class ScaleLinkService
{
    public const CONTENT_TYPE = 'application/octet-stream';

    private const UPLOAD_MINUTES = 30;

    public function __construct(
        private PatientScaleLinks $scaleLinks,
        private PatientScaleApplications $scaleApplications,
        private Patients $patients,
        private UserEncryptionKeys $userEncryptionKeys,
        private User $users,
        private FileService $fileService,
        private AccessLogService $accessLogService,
        private PlanLimitService $planLimitService = new PlanLimitService,
    ) {
    }

    /**
     * Gera o link do dia para o paciente e a escala. Se já houver um aguardando resposta, devolve o mesmo.
     */
    public function store(int $patientId, string $scale, int $userId, string $timezone): PatientScaleLinks
    {
        if (! $this->patients->findById(id: $patientId, userId: $userId, cols: ['id'])) {
            throw new \DomainException(__('scales.patient_not_found'));
        }

        if (! $this->userEncryptionKeys->findScalePublicKeyByUser($userId)) {
            throw new \DomainException(__('scales.scale_key_required'));
        }

        $today = $this->scaleLinks->findTodayByPatientAndScale(
            userId: $userId,
            patientId: $patientId,
            scale: $scale,
            since: now($timezone)->startOfDay()->utc(),
        );

        if ($today?->isPending()) {
            return $today;
        }

        if ($today) {
            throw new \DomainException(__('scales.link_daily_limit'));
        }

        $token = self::generateToken();

        // Só o link novo conta no limite do plano: devolver o pendente do dia não gasta saldo
        return DB::transaction(function () use ($userId, $patientId, $scale, $token) {
            $this->planLimitService->consume(userId: $userId, feature: FeatureUsageEnum::SCALE_LINKS);

            return $this->scaleLinks->create([
                'user_id' => $userId,
                'patient_id' => $patientId,
                'scale' => $scale,
                'token_hash' => self::hashToken($token),
                'token' => $token,
                'expires_at' => now()->addDays(PatientScaleLinks::VALID_DAYS),
            ]);
        });
    }

    public function paginateByPatient(int $patientId, int $userId, int $limit): LengthAwarePaginator
    {
        if (! $this->patients->findById(id: $patientId, userId: $userId, cols: ['id'])) {
            throw new \DomainException(__('scales.patient_not_found'));
        }

        return $this->scaleLinks->paginateByPatient(userId: $userId, patientId: $patientId, limit: $limit, cols: [
            'id', 'scale', 'token', 'expires_at', 'completed_at', 'revoked_at', 'created_at',
        ]);
    }

    public function revoke(int $id, int $userId): void
    {
        $link = $this->scaleLinks->findById(id: $id, userId: $userId);

        if (! $link) {
            throw new \DomainException(__('scales.link_not_found'));
        }

        if ($link->completed_at) {
            throw new \DomainException(__('scales.link_already_answered'));
        }

        if ($link->revoked_at) {
            return;
        }

        $link->revoked_at = now();

        if (! $link->save()) {
            throw new \DomainException(__('messages.failed_update'));
        }
    }

    /**
     * Dados da página pública: só a escala (itens e opções) e a chave pública. Nenhum dado pessoal.
     */
    public function findPublic(string $token): array
    {
        $link = $this->findPendingOrFail($token);

        return [
            'scale' => $link->scale->publicDefinition(),
            'public_key' => $this->publicKeyOrFail($link->user_id),
        ];
    }

    /**
     * Reserva o caminho do arquivo cifrado e devolve a URL pré-assinada para o navegador do paciente enviar ao S3.
     */
    public function issueUploadUrl(string $token): array
    {
        $link = $this->findPendingOrFail($token);
        $path = 'scale-applications/'.Str::uuid()->toString().'.bin';

        $upload = $this->fileService->temporaryUrlToUpload(path: $path, contentType: self::CONTENT_TYPE, minutes: self::UPLOAD_MINUTES);

        $link->content_path = $path;

        if (! $link->save()) {
            throw new \DomainException(__('messages.failed_update'));
        }

        return ['link' => $upload['url'], 'content_path' => $path];
    }

    /**
     * Confirma o envio: cria a aplicação da escala e encerra o link (não funciona mais depois disso).
     */
    public function complete(string $token): PatientScaleApplications
    {
        return DB::transaction(function () use ($token) {
            // lockForUpdate: dois envios simultâneos do mesmo link não geram duas aplicações.
            $link = $this->findPendingOrFail(token: $token, lock: true);

            if (! $link->content_path || ! $this->fileService->exists($link->content_path)) {
                throw new \DomainException(__('scales.link_upload_missing'));
            }

            $timezone = $this->users->findById($link->user_id)?->timezone ?: config('app.timezone');

            $application = $this->scaleApplications->create([
                'user_id' => $link->user_id,
                'patient_id' => $link->patient_id,
                'scale' => $link->scale->value,
                'applied_on' => now($timezone)->toDateString(),
                'content_path' => $link->content_path,
                'encryption' => ScaleEncryptionEnum::ECDH->value,
            ]);

            $link->completed_at = now();
            $link->scale_application_id = $application->id;

            if (! $link->save()) {
                throw new \DomainException(__('messages.failed_update'));
            }

            $this->accessLogService->registerScaleApplications(
                userId: $link->user_id,
                action: AccessLogActionEnum::SCALE_APPLICATION_ANSWERED,
                scaleApplicationIds: [$application->id],
            );

            return $application;
        });
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    // 256 bits aleatórios em base64url (43 caracteres): impossível de adivinhar ou enumerar.
    private static function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    // Inexistente, expirado, respondido, cancelado ou de paciente removido: a mesma resposta neutra.
    private function findPendingOrFail(string $token, bool $lock = false): PatientScaleLinks
    {
        $link = $this->scaleLinks->findByTokenHash(tokenHash: self::hashToken($token), lock: $lock);

        if (! $link || ! $link->isPending() || ! $this->patients->findById(id: $link->patient_id, userId: $link->user_id, cols: ['id'])) {
            throw new ScaleLinkUnavailableException(__('scales.link_unavailable'));
        }

        return $link;
    }

    private function publicKeyOrFail(int $userId): string
    {
        $publicKey = $this->userEncryptionKeys->findScalePublicKeyByUser($userId);

        if (! $publicKey) {
            throw new ScaleLinkUnavailableException(__('scales.link_unavailable'));
        }

        return $publicKey;
    }
}
