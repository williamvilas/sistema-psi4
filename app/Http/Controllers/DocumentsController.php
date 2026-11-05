<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AgendaStatusEnum;
use App\Enums\FeatureUsageEnum;
use App\Exceptions\PlanLimitException;
use App\Helpers\DocumentHelper;
use App\Helpers\UserHelper;
use App\Http\Requests\Documents\ByPatientRequest;
use App\Http\Requests\Documents\DefaultRequest;
use App\Http\Requests\Documents\DownloadRequest;
use App\Http\Requests\Documents\PaginateRequest;
use App\Http\Requests\Documents\PreviewRequest;
use App\Services\AgendaService;
use App\Services\DocumentFieldsService;
use App\Services\DocumentsService;
use App\Services\DocumentsTemplatesService;
use App\Services\GeneratePdfService;
use App\Services\GenerateWordService;
use App\Services\PlanLimitService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DocumentsController extends Controller
{
    public function paginate(PaginateRequest $request, DocumentsService $documentService): JsonResponse
    {
        return $this->response(function () use ($request, $documentService) {
            $params = $request->only(['q', 'type']);
            $docs = $documentService->paginate(params: $params, limit: config('app.paginate'), userId: Auth::id());

            return [
                'html' => view('documents.paginate', [
                    'docs' => $docs,
                    'search' => $params['q'] ?? null,
                    'hasFilters' => ! empty(array_filter($params)),
                ])->render(),
            ];
        });
    }

    public function preview(PreviewRequest $request, DocumentsTemplatesService $service): View
    {
        return $this->getPreviewView($request, $service);
    }

    public function pdf(
        PreviewRequest $request,
        DocumentsTemplatesService $service,
        GeneratePdfService $generatePdfService,
        PlanLimitService $planLimitService
    ): ResponseFactory|Response|JsonResponse {
        // Emissão que falhar na geração não gasta o saldo do plano
        try {
            return DB::transaction(function () use ($request, $service, $generatePdfService, $planLimitService) {
                $planLimitService->consume(userId: Auth::id(), feature: FeatureUsageEnum::DOCUMENTS);

                return $generatePdfService->handle(
                    html: $this->getPreviewView($request, $service)->render(),
                );
            });
        } catch (PlanLimitException $e) {
            return response()->json(['message' => $e->getMessage(), 'link' => route('plans.index', [], false)], 403);
        }
    }

    private function getPreviewView(PreviewRequest $request, DocumentsTemplatesService $service): View
    {
        $document = $service->getDocAndConfig(id: $request->getId(), userId: Auth::id());

        return view()->make($document['view'], [
            'data' => $request->all(),
            'configs' => $document['configs'],
        ]);
    }

    public function download(
        DownloadRequest $request,
        GenerateWordService $generateWordService,
        DocumentsService $documentService,
        DocumentsTemplatesService $templatesService,
        PlanLimitService $planLimitService
    ): ResponseFactory|Response|View|RedirectResponse {
        if (! $request->getId()) {
            return view('documents.not-found');
        }

        $userId = Auth::id();
        $doc = $documentService->getDoc(id: $request->getId(), userId: $userId);

        if (! $doc) {
            return view('documents.not-found');
        }

        $configs = $templatesService->getDocAndConfig(id: $request->getId(), userId: $userId)['configs'];
        $html = DocumentHelper::getContentDoc($doc, data: $request->all(), configs: $configs);

        try {
            return DB::transaction(function () use ($userId, $html, $generateWordService, $planLimitService) {
                $planLimitService->consume(userId: $userId, feature: FeatureUsageEnum::DOCUMENTS);

                return $generateWordService->handle(html: $html);
            });
        } catch (PlanLimitException) {
            return redirect()->route('plans.index');
        }
    }

    public function agendaByPatient(ByPatientRequest $request, AgendaService $service): JsonResponse
    {
        return $this->response(function () use ($request, $service) {
            $patientId = $request->getPatientId();
            $timezone = UserHelper::getTimezone();
            $start = $request->getStart($timezone);
            $end = $request->getEnd($timezone);

            $items = $service->byPatient(
                patientId: $patientId,
                userId: Auth::id(),
                perPage: $request->getPerPage(),
                start: $start,
                end: $end,
                status: AgendaStatusEnum::COMPLETED,
            );

            $data = [
                'timezone' => $timezone,
                'items' => $items,
                'patientId' => encrypt($patientId),
            ];

            return [
                'html' => view('documents.agenda-by-patient', $data)->render(),
            ];
        });
    }

    public function fields(PreviewRequest $request, DocumentFieldsService $service): JsonResponse
    {
        return $this->response(function () use ($request, $service) {
            $data = $service->fields(documentId: $request->getId(), userId: Auth::id());

            return [
                'has_agenda' => $data['has_agenda'],
                'html' => view('documents.fields', ['fields' => $data['fields']])->render(),
            ];
        });
    }

    public function firstSession(ByPatientRequest $request, DocumentFieldsService $service): JsonResponse
    {
        return $this->response(function () use ($request, $service) {
            $date = $service->firstSessionDate(
                patientId: $request->getPatientId(),
                userId: Auth::id(),
                timezone: UserHelper::getTimezone(),
            );

            return ['date' => $date ?? ''];
        });
    }

    public function saveDefault(DefaultRequest $request, DocumentFieldsService $service): JsonResponse
    {
        return $this->response(function () use ($request, $service) {
            $service->saveDefault(
                documentId: $request->getId(),
                userId: Auth::id(),
                fieldKey: $request->input('field_key'),
                value: $request->input('value'),
            );

            return $this->getMessageSuccess(message: __('documents.default_saved'));
        });
    }

    public function resetDefault(DefaultRequest $request, DocumentFieldsService $service): JsonResponse
    {
        return $this->response(function () use ($request, $service) {
            $value = $service->resetDefault(
                documentId: $request->getId(),
                userId: Auth::id(),
                fieldKey: $request->input('field_key'),
            );

            return [...$this->getMessageSuccess(message: __('documents.default_restored')), 'value' => $value];
        });
    }
}
