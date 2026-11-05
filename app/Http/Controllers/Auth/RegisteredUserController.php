<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Helpers\UserHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Jobs\GenerateDefaultSettingsJob;
use App\Models\User;
use App\Services\RegistrationInvitationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisteredUserController extends Controller
{
    public function store(RegisterRequest $request, RegistrationInvitationService $invitations): JsonResponse
    {
        try {
            // O convite é consumido na mesma transação: se já foi usado, a conta não é criada.
            $user = DB::transaction(function () use ($request, $invitations) {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'code' => UserHelper::generateCode(),
                    'timezone' => $request->timezone,
                    'terms_accepted_at' => now(),
                    'terms_version' => config('info.legal.terms_version'),
                ]);

                $invitations->consumeForUser($user);

                return $user;
            });
        } catch (\DomainException $d) {
            return response()->json(['message' => $d->getMessage(), 'link' => route('invite.request')], 403);
        }

        event(new Registered($user));
        GenerateDefaultSettingsJob::dispatchSync(userId: $user->id);

        Auth::login($user);
        UserHelper::updateLastLogin();

        $redirect = session('redirect');
        if ($redirect) {
            session()->forget('redirect');

            return response()->json(['link' => $redirect]);
        }

        return response()->json(['link' => route('dashboard', absolute: false)]);
    }
}
