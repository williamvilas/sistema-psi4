<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MedicalRecords\DeleteRequest;
use App\Http\Requests\MedicalRecords\EditRequest;
use App\Http\Requests\MedicalRecords\ExportRequest;
use App\Http\Requests\MedicalRecords\PaginateRequest;
use App\Http\Requests\MedicalRecords\ShowRequest;
use App\Http\Requests\MedicalRecords\StoreRequest;
use App\Http\Requests\MedicalRecords\UpdateRequest;
use App\Services\EncryptionKeyService;
use App\Services\MedicalRecordService;
use App\Services\PlanLimitService;
use App\Services\ScaleApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MedicalRecordsController extends Controller
{
    public function __construct(
        private readonly MedicalRecordService $medicalRecordService,
        private readonly EncryptionKeyService $encryptionKeyService,
        private readonly ScaleApplicationService $scaleApplicationService,
        private readonly PlanLimitService $planLimitService,
    ) {
    }

    public function create()
    {
        // Sem a senha mestre não há como cifrar o conteúdo: configura primeiro e volta para cá.
        if (! $this->encryptionKeyService->exists(Auth::id())) {
            return redirect()->route('encryption-key.index', ['redirect' => route('medical-records.create', [], false)]);
        }

        // Avisa antes de ela escrever: com a cifragem no navegador, barrar só no envio perderia a sessão escrita
        if ($this->planLimitService->hasReached(Auth::id(), 'total_medical_records')) {
            return view('medical-records.create', ['limitReached' => true]);
        }

        return view('medical-records.create');
    }

    public function paginate(PaginateRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $params = $request->getParams();
            $records = $this->medicalRecordService->paginate(
                userId: Auth::id(),
                limit: config('app.paginate'),
                params: $params,
            );

            return [
                'html' => view('medical-records.paginate', [
                    'records' => $records,
                    'search' => $params['q'] ?? null,
                    'hasFilters' => ! empty($params),
                ])->render(),
            ];
        });
    }

    public function store(StoreRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $record = $this->medicalRecordService->store(
                data: $request->toInsert(),
                userId: Auth::id(),
            );

            $id = encrypt($record->id);

            return [
                'link' => route('medical-records.show', ['id' => $id, 'm' => 'created_success']),
            ];
        });
    }

    public function update(UpdateRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $id = $request->getId();

            $this->medicalRecordService->update(
                id: $id,
                data: $request->toUpdate(),
                userId: Auth::id(),
            );

            return [
                'link' => route('medical-records.show', ['id' => encrypt($id), 'm' => 'updated_success']),
            ];
        });
    }

    public function show(ShowRequest $request)
    {
        $id = $request->getId();

        if (! $id) {
            return view('medical-records.show');
        }

        $record = $this->medicalRecordService->details(id: $id, userId: Auth::id());
        $this->isForbidden($record);

        return view('medical-records.show', [
            'record' => $record,
            'contentLink' => $this->medicalRecordService->getContentLink(record: $record, userId: Auth::id()),
        ]);
    }

    public function edit(EditRequest $request)
    {
        $id = $request->getId();
        $record = $id ? $this->medicalRecordService->details(id: $id, userId: Auth::id()) : null;
        $this->isForbidden($record);

        $data = [
            'record' => $record,
            'contentLink' => $this->medicalRecordService->getContentLink(record: $record, userId: Auth::id()),
        ];

        return view('medical-records.edit', $data);
    }

    public function delete(DeleteRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->medicalRecordService->delete(
                IDs: $request->getIDs(),
                userId: Auth::id(),
                force: $request->getForce(),
                retentionConfirmed: $request->getRetentionConfirmed(),
            );

            $response = $this->getMessageSuccess(message: __('messages.message_deleted'));
            $response['link'] = route('medical-records.index');

            return $response;
        });
    }

    // Exporta todos os prontuários e escalas do paciente (direito de acesso do paciente, encerramento da conta).
    public function export(ExportRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $user = Auth::user();
            $patientId = $request->getPatientId();

            if (! $patientId) {
                throw new \DomainException(__('medical-records.patient_not_found'));
            }

            return [
                ...$this->medicalRecordService->exportByPatient(patientId: $patientId, userId: $user->id),
                'scales' => $this->scaleApplicationService->exportByPatient(patientId: $patientId, userId: $user->id),
            ];
        });
    }
}
