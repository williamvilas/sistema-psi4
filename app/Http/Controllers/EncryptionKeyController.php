<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\UserHelper;
use App\Http\Requests\EncryptionKey\StoreRequest;
use App\Http\Requests\EncryptionKey\StoreScaleKeyPairRequest;
use App\Http\Requests\EncryptionKey\UpdatePassphraseRequest;
use App\Http\Requests\EncryptionKey\UpdateRecoveryRequest;
use App\Services\EncryptionKeyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EncryptionKeyController extends Controller
{
    public function __construct(private readonly EncryptionKeyService $service)
    {
    }

    public function index(Request $request): View
    {
        return view('encryption-key.index', [
            'exists' => $this->service->exists(Auth::id()),
            'redirect' => UserHelper::sanitizeRedirect($request->query('redirect')),
        ]);
    }

    public function show(): JsonResponse
    {
        return $this->response(function () {
            $details = $this->service->details(Auth::id());

            if (! $details) {
                throw new \DomainException(__('encryption-key.not_found'));
            }

            return $details;
        });
    }

    public function store(StoreRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->service->store(userId: Auth::id(), data: $request->toInsert());

            return $this->getMessageSuccess();
        });
    }

    public function storeScaleKeyPair(StoreScaleKeyPairRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->service->storeScaleKeyPair(
                userId: Auth::id(),
                publicKey: $request->getPublicKey(),
                wrappedPrivateKey: $request->getWrappedPrivateKey(),
            );

            return $this->getMessageSuccess();
        });
    }

    public function updatePassphrase(UpdatePassphraseRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->service->updatePassphrase(userId: Auth::id(), data: $request->toUpdate());

            return $this->getMessageSuccess();
        });
    }

    public function updateRecovery(UpdateRecoveryRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->service->updateRecovery(userId: Auth::id(), data: $request->toUpdate());

            return $this->getMessageSuccess();
        });
    }
}
