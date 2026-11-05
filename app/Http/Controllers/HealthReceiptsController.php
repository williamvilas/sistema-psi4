<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\UserHelper;
use App\Http\Requests\HealthReceipts\DeleteRequest;
use App\Http\Requests\HealthReceipts\PaginateRequest;
use App\Http\Requests\HealthReceipts\RestoreRequest;
use App\Http\Requests\HealthReceipts\SkipRequest;
use App\Http\Requests\HealthReceipts\StoreRequest;
use App\Http\Requests\HealthReceipts\UpdateRequest;
use App\Services\HealthReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class HealthReceiptsController extends Controller
{
    public function __construct(private readonly HealthReceiptService $healthReceiptService)
    {
    }

    public function index()
    {
        return view('health-receipts.index');
    }

    public function paginate(PaginateRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $paginate = $this->healthReceiptService->paginate(
                params: $request->toParams(),
                userId: Auth::id(),
                limit: config('app.paginate'),
                patientId: $request->getPatientId(),
            );

            $overview = $this->healthReceiptService->overview(user: Auth::user(), timezone: UserHelper::getTimezone());

            return [
                'html' => view('health-receipts.paginate', ['receipts' => $paginate])->render(),
                'overview' => view('health-receipts.partials.overview', ['overview' => $overview])->render(),
            ];
        });
    }

    public function store(StoreRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->healthReceiptService->store(data: $request->toPayload(), userId: Auth::id());

            return $this->getMessageSuccess(message: __('health-receipts.stored_success'));
        });
    }

    public function update(UpdateRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->healthReceiptService->update(id: $request->getId() ?? 0, data: $request->toUpdate(), userId: Auth::id());

            return $this->getMessageSuccess(message: __('health-receipts.updated_success'));
        });
    }

    public function delete(DeleteRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->healthReceiptService->delete(id: $request->getId() ?? 0, userId: Auth::id());

            return $this->getMessageSuccess(message: __('health-receipts.deleted_success'));
        });
    }

    public function skip(SkipRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->healthReceiptService->skip(id: $request->getId() ?? 0, userId: Auth::id());

            return $this->getMessageSuccess(message: __('health-receipts.skipped_success'));
        });
    }

    public function restore(RestoreRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->healthReceiptService->restore(id: $request->getId() ?? 0, userId: Auth::id());

            return $this->getMessageSuccess(message: __('health-receipts.restored_success'));
        });
    }
}
