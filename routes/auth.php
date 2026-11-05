<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\RegistrationLeadsController;
use App\Http\Middleware\EnsureRegistrationAllowed;
use App\Http\Middleware\ForceChangePassword;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', function () {
        return view('auth.register');
    })->middleware(EnsureRegistrationAllowed::class)->name('register');

    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware([EnsureRegistrationAllowed::class, 'throttle:10,1']);

    Route::get('convite', [RegistrationLeadsController::class, 'create'])->name('invite.request');
    Route::post('convite', [RegistrationLeadsController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('invite.request.store');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::prefix('auth/{provider}')->whereIn('provider', ['google', 'linkedin'])->group(function () {
        Route::get('/', [SocialAuthController::class, 'redirect'])->name('social.redirect');
        Route::get('callback', [SocialAuthController::class, 'callback'])->name('social.callback');
    });

    Route::prefix('two-factor')->group(function () {
        Route::get('challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
        Route::post('challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:20,1');
        Route::post('challenge/reset-link', [TwoFactorChallengeController::class, 'sendResetLink'])
            ->middleware('throttle:3,10')
            ->name('two-factor.reset-link');
    });

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,10')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('password.store');
});

Route::prefix('two-factor/reset/{user}/{token}')->middleware(['signed', 'throttle:10,1'])->group(function () {
    Route::get('/', [TwoFactorChallengeController::class, 'showReset'])->name('two-factor.reset.show');
    Route::post('/', [TwoFactorChallengeController::class, 'confirmReset'])->name('two-factor.reset.confirm');
});

Route::middleware(['auth', ForceChangePassword::class])->group(function () {
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    Route::put('force-change-password', [PasswordController::class, 'forceChangePassword'])->name('password.forceChangePassword');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
