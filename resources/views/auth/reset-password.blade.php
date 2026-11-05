@extends('layouts.guest')
@section('title', 'Redefinir Senha')
@section('content')
    @include('auth.partials.recovery-styles')
    <div class="tf-wrapper">
        <div class="tf-card">
            <a href="{{ route('login') }}" class="d-inline-block mb-3 py-1" style="font-size: 1rem;">
                <i class="fa-solid fa-arrow-left"></i>
                Voltar
            </a>
            <div class="card card-border-color card-border-color-primary">
                <div class="card-header text-center">
                    <div class="mb-3 d-flex justify-content-center" aria-label="{{ config('app.name') }}">
                        <x-logo-psi4 :size="48" text="#1b323c" accent="#2fa595" />
                    </div>
                    <h2 class="login-title">Criar nova senha</h2>
                    <p class="splash-description">
                        Escolha uma nova senha para acessar a sua conta.
                    </p>
                </div>
                <div class="card-body">
                    <form id="form-reset-password">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <input type="hidden" name="email" value="{{ $email }}">
                        <div class="login-form">
                            @if ($email)
                                <p class="text-center text-muted mb-3">
                                    <i class="fa-regular fa-envelope me-1"></i>{{ $email }}
                                </p>
                            @else
                                <div class="form-group mb-3">
                                    <label for="email-visible" class="form-label">E-mail</label>
                                    <input class="form-control" id="email-visible" type="email" autocomplete="username" required>
                                </div>
                            @endif
                            <div class="form-group">
                                <label for="password" class="form-label">Nova senha</label>
                                <div class="tf-password">
                                    <input class="form-control" id="password" name="password" type="password"
                                           autocomplete="new-password" minlength="8" autofocus required>
                                    <button type="button" class="tf-toggle btn-toggle-password" aria-label="Mostrar ou ocultar senha">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                                <div class="tf-hint">Use pelo menos 8 caracteres.</div>
                            </div>
                            <div class="form-group mt-3">
                                <label for="password_confirmation" class="form-label">Confirmar nova senha</label>
                                <div class="tf-password">
                                    <input class="form-control" id="password_confirmation" name="password_confirmation"
                                           type="password" autocomplete="new-password" required>
                                    <button type="button" class="tf-toggle btn-toggle-password" aria-label="Mostrar ou ocultar senha">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group row login-submit mt-4">
                                <div class="col-12">
                                    <button class="btn btn-primary btn-lg w-100" type="submit">Redefinir senha</button>
                                </div>
                            </div>
                            <div class="text-center mt-3 tf-links">
                                <a href="{{ route('password.request') }}">Solicitar um novo link</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
