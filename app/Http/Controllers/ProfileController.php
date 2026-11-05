<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\UserHelper;
use App\Http\Requests\Profile\UpdatePhotoRequest;
use App\Http\Requests\Profile\UpdateRequest;
use App\Services\PhotoUserService;
use App\Services\ProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $service)
    {
    }

    public function edit(): View
    {
        $user = Auth::user();

        return view('auth.profile', [
            'user' => $user,
            'currentPlan' => UserHelper::getCurrentPlan($user),
            'photo' => $user->photo ? UserHelper::getPhoto($user) : null,
            'twoFactorEnabled' => $user->hasTwoFactorEnabled(),
        ]);
    }

    public function update(UpdateRequest $request): JsonResponse
    {
        return $this->response(function () use ($request) {
            $user = $this->service->update(Auth::user(), $request->payload());

            $response = $this->getMessageSuccess();
            $response['data'] = [
                'name' => UserHelper::getFirstName($user),
                'full_name' => $user->name,
                'email' => $user->email,
            ];

            return $response;
        });
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function updatePhoto(UpdatePhotoRequest $request, PhotoUserService $service): JsonResponse
    {
        return $this->response(function () use ($request, $service) {
            $service->handle(user: Auth::user(), file: $request->file('photo'));
            UserHelper::removeCaches(Auth::user());

            $data = $this->getMessageSuccess();
            $data['data'] = [
                'photo' => UserHelper::getPhoto(),
            ];

            return $data;
        });
    }
}
