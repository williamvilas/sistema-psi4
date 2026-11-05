<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Plans\CheckoutRequest;
use App\Services\PlanSubscriptionService;
use App\Services\SessionTranscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class PlansController extends Controller
{
    public function __construct(
        private readonly PlanSubscriptionService $planSubscriptionService,
    ) {
    }

    public function index(): View
    {
        return view('plans.index', [
            'plans' => $this->planSubscriptionService->getPlansForDisplay(Auth::user()),
            'pendingSubscription' => $this->planSubscriptionService->getPendingCheckout(Auth::user()),
            'transcriptionUsage' => SessionTranscriptionService::isEnabled() ? app(SessionTranscriptionService::class)->usage(Auth::id()) : null,
        ]);
    }

    public function options(string $plan): View|RedirectResponse
    {
        try {
            $options = $this->planSubscriptionService->getCheckoutOptions($plan, Auth::user());
        } catch (\DomainException) {
            // Acesso direto pela URL sem poder contratar: a tela de planos já explica o motivo (plano atual, pagamento pendente)
            return redirect()->route('plans.index');
        }

        return view('plans.options', ['planKey' => $plan, 'plan' => config('plans.plans.'.$plan), 'options' => $options]);
    }

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $link = $this->planSubscriptionService->checkout(
                plan: $request->getPlan(),
                user: Auth::user(),
                cycle: $request->getCycle(),
                paymentMethod: $request->getPaymentMethod(),
            );

            return ['link' => $link];
        });
    }

    public function cancelRenewal(): JsonResponse
    {
        return $this->response(function () {
            $expirationDate = $this->planSubscriptionService->cancelRenewal(Auth::user());

            return $this->getMessageSuccess(message: __('plans.cancel_renewal_success', ['date' => $expirationDate]));
        });
    }
}
