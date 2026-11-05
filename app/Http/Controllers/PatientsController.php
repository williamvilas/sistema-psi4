<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\FeatureUsageEnum;
use App\Helpers\UserHelper;
use App\Http\Requests\Patients\DeleteRequest;
use App\Http\Requests\Patients\EditRequest;
use App\Http\Requests\Patients\EvolutionRequest;
use App\Http\Requests\Patients\ExistsRequest;
use App\Http\Requests\Patients\PaginateRequest;
use App\Http\Requests\Patients\ShowRequest;
use App\Http\Requests\Patients\StoreRequest;
use App\Http\Requests\Patients\UpdateRequest;
use App\Jobs\SaveFeatureUsageJob;
use App\Services\PatientEvolutionService;
use App\Services\PatientService;
use App\Services\PsychologistSettingService;
use App\Services\ScaleApplicationService;
use App\Services\SessionTranscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientsController extends Controller
{
    public function __construct(
        private readonly PatientService $patientService,
        private readonly PsychologistSettingService $psychologistSettingService,
        private readonly PatientEvolutionService $patientEvolutionService,
        private readonly ScaleApplicationService $scaleApplicationService,
    ) {
    }

    public function edit(EditRequest $request): View
    {
        $id = $request->getId();

        if (! $id) {
            return view('patients.edit');
        }

        $patient = $this->patientService->details(id: $id, userId: Auth::id());

        abort_if(empty($patient), 404);

        return view('patients.edit', ['patient' => $patient]);
    }

    public function store(StoreRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $p = $this->patientService->store(data: $request->toInsert(), user: Auth::user());
            SaveFeatureUsageJob::dispatch(userId: Auth::id(), feature: FeatureUsageEnum::PATIENTS);
            $id = encrypt($p->id);

            return [
                'link' => route('patients.show', ['id' => $id, 'm' => 'created_success']),
                'data' => [
                    'id' => $id,
                ],
            ];
        });
    }

    public function update(UpdateRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $id = $request->getId();
            $this->patientService->update(id: $id, data: $request->toUpdate(), userId: Auth::id());

            return [
                'link' => route('patients.show', ['id' => encrypt($id), 'm' => 'updated_success']),
            ];
        });
    }

    public function delete(DeleteRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->patientService->delete(IDs: $request->getIDs(), userId: Auth::id());

            $response = $this->getMessageSuccess(message: __('messages.message_deleted'));
            $response['link'] = route('patients.index');

            return $response;
        });
    }

    public function paginate(PaginateRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $params = $request->validated();
            $patients = $this->patientService->paginate(
                userId: Auth::id(),
                limit: config('app.paginate'),
                params: $params,
            );

            return [
                'html' => view('patients.paginate', [
                    'patients' => $patients,
                    'search' => $params['q'] ?? null,
                    'hasFilters' => ! empty(array_filter($params)),
                ])->render(),
            ];
        });
    }

    public function show(ShowRequest $request)
    {
        $id = $request->getId();

        if (! $id) {
            return view('patients.show');
        }

        $patient = $this->patientService->details(id: $id, userId: Auth::id());

        abort_if(empty($patient), 404);

        return view('patients.show', [
            'patient' => $patient,
            'crp' => $this->psychologistSettingService->details(userId: Auth::id())?->crp,
            'scales' => $this->scaleApplicationService->catalog(),
            'evolutionPeriods' => PatientEvolutionService::PERIODS,
            'pendingTranscriptions' => app(SessionTranscriptionService::class)->countPendingByPatient($patient->id, Auth::id()),
        ]);
    }

    // Indicadores de frequência do painel de evolução (as escalas são decifradas no navegador).
    public function evolution(EvolutionRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $timezone = UserHelper::getTimezone();

            $attendance = $this->patientEvolutionService->attendance(
                patientId: (int) $request->getId(),
                userId: Auth::id(),
                timezone: $timezone,
                months: $request->getPeriod(),
            );

            return [
                'html' => view('patients.partials.evolution-indicators', ['attendance' => $attendance, 'timezone' => $timezone])->render(),
            ];
        });
    }

    public function exists(ExistsRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $patient = $this->patientService->getPatientByPhone(phone: $request->getPhone(), userId: Auth::id());
            $currentDate = UserHelper::getDateCurrent();
            $isToday = ! empty($patient?->birthday_md) && $patient->birthday_md == $currentDate->format('m-d');

            $payload = [
                'patient' => $patient,
                'currentDate' => $currentDate,
                'phone' => (string) $request->getPhone(),
                'isToday' => $isToday,
            ];

            return [
                'isToday' => $isToday,
                'exists' => ! empty($patient),
                'html' => view('patients.exists', $payload)->render(),
            ];
        });
    }

    public function autocomplete(Request $request): JsonResponse
    {
        $patients = $this->patientService->autocomplete(q: $request->query('q'), userId: Auth::id());

        return response()->json($patients);
    }
}
