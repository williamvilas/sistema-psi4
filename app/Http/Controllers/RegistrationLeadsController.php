<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RegistrationLeadsController\StoreRequest;
use App\Services\RegistrationInvitationService;
use App\Services\RegistrationLeadService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class RegistrationLeadsController extends Controller
{
    public function create(RegistrationInvitationService $invitations): View|RedirectResponse
    {
        if (! $invitations->isInviteOnly()) {
            return redirect()->route('register');
        }

        return view('auth.request-invite');
    }

    public function store(StoreRequest $request, RegistrationInvitationService $invitations, RegistrationLeadService $service): JsonResponse
    {
        if (! $invitations->isInviteOnly()) {
            return response()->json(['link' => route('register')]);
        }

        // Bot que preencheu o honeypot recebe a mesma resposta, sem gravar nada.
        if (! $request->filled('website')) {
            $service->store($request->validated());
        }

        return response()->json(['message' => __('invitations.requested')]);
    }
}
