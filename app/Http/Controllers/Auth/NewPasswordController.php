<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewPasswordController extends Controller
{
    public function __construct(private PasswordResetService $service)
    {
    }

    public function create(Request $request): View
    {
        return view('auth.reset-password', [
            'email' => (string) $request->query('email'),
            'token' => (string) $request->route('token'),
        ]);
    }

    public function store(ResetPasswordRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $this->service->reset($request->validated());

            return [
                'title' => __('messages.title_success'),
                'message' => __('password-reset.reset_done'),
                'link' => route('login', absolute: false),
            ];
        });
    }
}
