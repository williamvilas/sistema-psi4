<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class PasswordResetLinkController extends Controller
{
    public function __construct(private PasswordResetService $service)
    {
    }

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->service->sendResetLink($request->getEmail());

            return ['title' => __('messages.title_success'), 'message' => __('password-reset.link_sent')];
        });
    }
}
