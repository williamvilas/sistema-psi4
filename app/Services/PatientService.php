<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaginateCacheContextEnum;
use App\Helpers\DashboardCache;
use App\Helpers\DateTimeHelper;
use App\Helpers\NumberHelper;
use App\Helpers\PaginateCache;
use App\Helpers\StringHelper;
use App\Helpers\UserHelper;
use App\Jobs\CancelOpenAgendaJob;
use App\Jobs\RegisterLog;
use App\Models\Patients;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

readonly class PatientService
{
    public function __construct(
        private Patients $patients,
        private UserHelper $userHelper,
        private NumberHelper $numberHelper,
        private DateTimeHelper $dateTimeHelper,
    ) {
    }

    public function store(array $data, User $user): Patients
    {
        $document = null;

        if (! empty($data['document'])) {

            $document = $this->numberHelper::onlyNumbers($data['document']);
            $patient = $this->patients->getPatient(document: $document, userId: $user->id);

            if ($patient) {
                throw ValidationException::withMessages(['document' => __('patients.patient_exists')]);
            }
        }

        $phone = $this->numberHelper::onlyNumbers($data['phone']);
        $patient = $this->patients->getPatientByPhone(phone: $phone, userId: $user->id);

        if ($patient) {
            throw ValidationException::withMessages(['phone' => __('patients.patient_exists_phone')]);
        }

        $birthDate = ! empty($data['birth_date']) ? $data['birth_date'] : null;

        try {
            return $this->patients->create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'document' => $document,
                'payer_name' => $this->payerName($data),
                'payer_document' => $this->payerDocument($data),
                'phone' => $phone,
                'birth_date' => $birthDate ? $this->dateTimeHelper::convertToDatabase($birthDate, 'Y-m-d') : null,
                'birthday_md' => $birthDate ? $this->dateTimeHelper::convertToDatabase($birthDate, 'm-d') : null,
                'email' => $data['email'] ?? null,
                'whatsapp_notifications' => (bool) ($data['whatsapp_notifications'] ?? true),
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Telefone duplicado entre pacientes ativos (corrida entre requisições)
            throw ValidationException::withMessages(['phone' => __('patients.patient_exists_phone')]);
        }
    }

    public function update(int $id, array $data, int $userId): void
    {
        $document = null;

        if (! empty($data['document'])) {

            $document = $this->numberHelper::onlyNumbers($data['document']);
            $patient = $this->patients->getPatient(document: $document, userId: $userId);

            if ($patient && $patient->id !== $id) {
                throw ValidationException::withMessages(['document' => __('patients.patient_exists')]);
            }
        }

        $phone = $this->numberHelper::onlyNumbers($data['phone']);
        $patient = $this->patients->getPatientByPhone(phone: $phone, userId: $userId);

        if ($patient && $patient->id !== $id) {
            throw ValidationException::withMessages(['phone' => __('patients.patient_exists_phone')]);
        }

        $birthDate = ! empty($data['birth_date']) ? $data['birth_date'] : null;

        $payload = [
            'name' => $data['name'],
            'document' => $document,
            'payer_name' => $this->payerName($data),
            'payer_document' => $this->payerDocument($data),
            'phone' => $phone,
            'birth_date' => $birthDate ? $this->dateTimeHelper::convertToDatabase($birthDate, 'Y-m-d') : null,
            'birthday_md' => $birthDate ? $this->dateTimeHelper::convertToDatabase($birthDate, 'm-d') : null,
            'email' => $data['email'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        // Só quem envia o campo (formulário do paciente) altera os avisos: o paciente pode ter saído respondendo SAIR
        if (array_key_exists('whatsapp_notifications', $data)) {
            $payload['whatsapp_notifications'] = (bool) $data['whatsapp_notifications'];
        }

        try {
            $r = $this->patients->updateData(id: $id, data: $payload, userId: $userId);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['phone' => __('patients.patient_exists_phone')]);
        }

        // Update em massa não dispara o observer, então o cache é limpo aqui
        PaginateCache::forgetPatientDependents($userId);
        DashboardCache::forget($userId);

        if (! $r) {
            $m = __('messages.failed_update');

            RegisterLog::dispatchSync([
                'data' => array_merge($data, ['id' => $id]),
                'message' => $m,
                'method' => 'PatientService->update',
            ]);

            throw new \DomainException($m);
        }
    }

    public function paginate(int $userId, int $limit = 15, array $params = []): LengthAwarePaginator
    {
        return PaginateCache::remember(
            context: PaginateCacheContextEnum::PATIENTS,
            userId: $userId,
            params: [
                'q' => $params['q'] ?? null,
                'birthday_celebrants' => $params['birthday_celebrants'] ?? null,
                'limit' => $limit,
            ],
            callback: fn () => $this->queryPaginate($userId, $limit, $params),
        );
    }

    private function queryPaginate(int $userId, int $limit, array $params): LengthAwarePaginator
    {
        return $this->patients->newQuery()
            ->select(['id', 'name', 'phone', 'birth_date', 'birthday_md', 'created_at'])
            ->withDuplicatePhone()
            ->where('user_id', $userId)
            ->when(! empty($params['q']), function ($q) use ($params) {
                $search = preg_replace('/[^\w\s]/', '', $params['q']);
                $number = $this->numberHelper::onlyNumbers($search);

                $q->where(function ($query) use ($search, $number) {
                    $query->orWhere('name', 'LIKE', '%'.addcslashes($search, '%_\\').'%');

                    // Busca numérica
                    if (! empty($number)) {
                        $query->orWhere('phone', 'LIKE', "{$number}%")
                            ->orWhere('document', 'LIKE', "{$number}%");
                    }
                });
            })
            ->when(! empty($params['birthday_celebrants']), function ($q) use ($params) {
                $value = $params['birthday_celebrants'];

                if (str_contains($value, ' - ')) {
                    [$start, $end] = explode(' - ', $value, 2);
                    $start = DateTimeHelper::convertToDatabase(dateInput: trim($start), format: 'm-d');
                    $end = DateTimeHelper::convertToDatabase(dateInput: trim($end), format: 'm-d');

                    if ($start <= $end) {
                        $q->whereBetween('birthday_md', [$start, $end]);

                        return;
                    }

                    // Período que atravessa a virada do ano (ex.: 20/12 a 05/01)
                    $q->where(function ($query) use ($start, $end) {
                        $query->where('birthday_md', '>=', $start)->orWhere('birthday_md', '<=', $end);
                    });
                }
            })
            ->orderByDesc('created_at')
            ->paginate($limit)
            ->withQueryString();
    }

    public function delete(array $IDs, int $userId): void
    {
        $ids = [];

        foreach ($IDs as $id) {
            $decrypted = is_string($id) ? StringHelper::decrypt($id) : null;

            if ($decrypted) {
                $ids[(int) $decrypted] = (int) $decrypted;
            }
        }

        $ids = array_values($ids);

        try {
            DB::transaction(function () use ($ids, $userId) {
                foreach (array_chunk($ids, 500) as $chunk) {
                    $this->patients->deleteInBatch(id: $chunk, userId: $userId);
                }
            });

            if (! empty($ids)) {
                // Delete em massa não dispara o observer, então o cache é limpo aqui
                PaginateCache::forgetPatientDependents($userId);
                DashboardCache::forget($userId);
                CancelOpenAgendaJob::dispatch(userId: $userId, patientIDs: $ids);
            }
        } catch (\Throwable $t) {
            RegisterLog::dispatchSync([
                'data' => $IDs,
                'message' => $t->getMessage(),
                'method' => 'PatientService->delete',
            ]);

            throw new \DomainException(__('messages.failed_delete'));
        }
    }

    public function details(int $id, int $userId): ?Patients
    {
        $cols = [
            'id',
            'name',
            'birth_date',
            'birthday_md',
            'document',
            'payer_name',
            'payer_document',
            'phone',
            'email',
            'whatsapp_notifications',
            'notes',
            'created_at',
        ];

        $patient = $this->patients->findById(id: $id, userId: $userId, cols: $cols);

        if ($patient) {
            // Telefone repetido entre pacientes (ex.: agendamento self-service de familiares)
            $patient->setAttribute('duplicate_phone_count', $this->patients->countSamePhone(
                phone: (string) $patient->phone,
                userId: $userId,
                exceptId: (int) $patient->id,
            ));
        }

        return $patient;
    }

    /**
     * Dados mínimos para pré-selecionar o paciente num select (ex.: nova cobrança a partir da ficha).
     */
    public function findForSelect(int $id, int $userId): ?Patients
    {
        return $this->patients->findById(id: $id, userId: $userId, cols: ['id', 'name', 'phone']);
    }

    public function getPatientByPhone(?string $phone, int $userId): ?Patients
    {
        $phone = $this->numberHelper::onlyNumbers((string) $phone);

        if ($phone === '') {
            return null;
        }

        $cols = ['id', 'name', 'birth_date', 'birthday_md', 'document', 'phone'];

        return $this->patients->getPatientByPhone(phone: $phone, userId: $userId, cols: $cols);
    }

    public function autocomplete(?string $q, int $userId): array
    {
        $q = trim((string) $q);

        if ($q === '') {
            return [];
        }

        $patients = $this->patients->autocomplete(q: $q, userId: $userId);
        $new = [];

        foreach ($patients as $p) {
            $new[] = [
                'id' => encrypt($p->id),
                'name' => $p->name,
                'phone' => $p->phone,
            ];
        }

        return $new;
    }

    /**
     * Responsável financeiro (quem paga as sessões): o CPF dele vai como pagador no recibo do Receita Saúde.
     */
    private function payerName(array $data): ?string
    {
        return ! empty($data['payer_name']) ? trim((string) $data['payer_name']) : null;
    }

    private function payerDocument(array $data): ?string
    {
        return ! empty($data['payer_document']) ? $this->numberHelper::onlyNumbers($data['payer_document']) : null;
    }
}
