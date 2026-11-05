<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ScaleApplications\DeleteRequest;
use App\Http\Requests\ScaleApplications\ListRequest;
use App\Http\Requests\ScaleApplications\StoreRequest;
use App\Services\ScaleApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ScaleApplicationsController extends Controller
{
    public function __construct(
        private readonly ScaleApplicationService $scaleApplicationService,
    ) {
    }

    public function store(StoreRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->scaleApplicationService->store(data: $request->toInsert(), userId: Auth::id());

            return $this->getMessageSuccess();
        });
    }

    public function list(ListRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            return [
                'applications' => $this->scaleApplicationService->listByPatient(patientId: $request->getPatientId(), userId: Auth::id()),
            ];
        });
    }

    public function delete(DeleteRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->scaleApplicationService->delete(
                id: $request->getId(),
                userId: Auth::id(),
                retentionConfirmed: $request->getRetentionConfirmed(),
            );

            return $this->getMessageSuccess(message: __('messages.message_deleted'));
        });
    }
}
