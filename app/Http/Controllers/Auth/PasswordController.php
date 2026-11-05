<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        Auth::logoutOtherDevices($validated['password']);

        return $this->success($this->getMessageSuccess(message: __('profile.password_updated')));
    }

    public function forceChangePassword(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'force_change_password' => 0,
            'temporary_password_expiration' => null,
        ]);

        Auth::logoutOtherDevices($validated['password']);

        return response()->json([
            'title' => 'Atualizado!',
            'message' => 'Senha atualizada com sucesso!',
            'url' => route('dashboard'),
        ]);
    }
}
