<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PaymentAccount\CallbackRequest;
use App\Services\PaymentAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentAccountController extends Controller
{
    public function __construct(private readonly PaymentAccountService $paymentAccountService)
    {
    }

    /**
     * Redireciona o terapeuta para a tela de autorização do Mercado Pago
     * (Mercado Pago Connect).
     */
    public function connect(): RedirectResponse
    {
        $url = $this->paymentAccountService->buildAuthorizationUrl(Auth::id());

        return redirect()->away($url);
    }

    /**
     * Callback OAuth: troca o `code` pelos tokens da conta conectada e volta
     * para a tela de configurações com o resultado.
     */
    public function callback(CallbackRequest $request): RedirectResponse
    {
        if ($request->getError()) {
            return $this->redirectToSettings('denied');
        }

        try {
            $this->paymentAccountService->connect(
                code: (string) $request->getCode(),
                state: $request->getState(),
                userId: Auth::id(),
            );
        } catch (\DomainException $d) {
            return $this->redirectToSettings('error', $d->getMessage());
        } catch (\Throwable $t) {
            Log::error('Falha inesperada ao conectar conta do Mercado Pago (Connect).', [
                'user_id' => Auth::id(),
                'exception' => $t->getMessage(),
                'class' => $t::class,
            ]);

            return $this->redirectToSettings('error', __('payments.oauth_failed'));
        }

        return $this->redirectToSettings('connected');
    }

    public function disconnect(): JsonResponse
    {
        return $this->response(function () {
            $this->paymentAccountService->disconnect(Auth::id());

            return $this->getMessageSuccess();
        });
    }

    private function redirectToSettings(string $status, ?string $message = null): RedirectResponse
    {
        return redirect()->route('settings.index', array_filter([
            'mp' => $status,
            'mp_message' => $message,
        ]));
    }
}
