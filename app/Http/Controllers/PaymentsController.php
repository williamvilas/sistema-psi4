<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentAccountsStatusEnum;
use App\Helpers\UserHelper;
use App\Http\Requests\Payments\CreateRequest;
use App\Http\Requests\Payments\DeleteRequest;
use App\Http\Requests\Payments\EditRequest;
use App\Http\Requests\Payments\PaginateRequest;
use App\Http\Requests\Payments\RefundRequest;
use App\Http\Requests\Payments\ShowRequest;
use App\Http\Requests\Payments\StoreRequest;
use App\Http\Requests\Payments\UpdateRequest;
use App\Services\PatientService;
use App\Services\PaymentAccountService;
use App\Services\PaymentOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PaymentsController extends Controller
{
    public function __construct(
        private readonly PaymentOrderService $paymentOrderService,
        private readonly PaymentAccountService $paymentAccountService,
        private readonly PatientService $patientService,
    ) {
    }

    public function index()
    {
        $paymentAccount = $this->paymentAccountService->details(userId: Auth::id());
        $isAccountIntegrated = $paymentAccount?->status?->value === PaymentAccountsStatusEnum::INTEGRATED->value;

        return view('payments.index', [
            'paymentAccount' => $paymentAccount,
            'showIntro' => ! $isAccountIntegrated && ! $this->paymentOrderService->hasOrders(userId: Auth::id()),
        ]);
    }

    public function create(CreateRequest $request)
    {
        $patientId = $request->getPatientId();

        return view('payments.create', [
            'paymentAccount' => $this->paymentAccountService->details(userId: Auth::id()),
            'patient' => $patientId ? $this->patientService->findForSelect(id: $patientId, userId: Auth::id()) : null,
        ]);
    }

    public function paginate(PaginateRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $paginate = $this->paymentOrderService->paginate(
                params: $request->validated(),
                userId: Auth::id(),
                limit: config('app.paginate'),
                patientId: $request->getPatientId(),
            );

            $summary = $this->paymentOrderService->summary(userId: Auth::id(), timezone: UserHelper::getTimezone());

            return [
                'html' => view('payments.paginate', ['orders' => $paginate])->render(),
                'summary' => view('payments.partials.summary', ['summary' => $summary])->render(),
            ];
        });
    }

    public function show(ShowRequest $request)
    {
        $id = $request->getId();

        if (! $id) {
            return view('payments.show');
        }

        $order = $this->paymentOrderService->details(id: $id, userId: Auth::id());

        return view('payments.show', ['order' => $order]);
    }

    public function edit(EditRequest $request)
    {
        $id = $request->getId();

        if (! $id) {
            return view('payments.edit');
        }

        $order = $this->paymentOrderService->details(id: $id, userId: Auth::id());

        return view('payments.edit', [
            'order' => $order,
            'paymentAccount' => $this->paymentAccountService->details(userId: Auth::id()),
        ]);
    }

    public function update(UpdateRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $id = $request->getId();
            $this->paymentOrderService->update(id: $id, data: $request->toUpdate(), userId: Auth::id());

            return [
                'link' => route('payments.show', ['id' => encrypt($id), 'm' => 'updated_success']),
            ];
        });
    }

    public function delete(DeleteRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->paymentOrderService->delete(IDs: $request->getIDs(), userId: Auth::id());

            $response = $this->getMessageSuccess(message: __('messages.message_deleted'));
            $response['link'] = route('payments.index');

            return $response;
        });
    }

    public function refund(RefundRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->paymentOrderService->refund(id: $request->getId(), userId: Auth::id());

            return $this->getMessageSuccess(message: __('payments.refund_success'));
        });
    }

    public function store(StoreRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->paymentOrderService->store(data: $request->toPayload(), userId: Auth::id());

            return [
                'link' => route('payments.index', ['m' => 'created_success']),
            ];
        });
    }
}
