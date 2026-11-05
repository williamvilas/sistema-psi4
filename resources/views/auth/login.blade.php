@extends('layouts.guest-fullpage')
@section('title', 'Login')

@section('header')
<style>
    .btn:hover {color: #6c757d !important;}
    #password:focus {border-right: 0;}
    .btn-toggle-password{border-radius: 0 14px 14px 0; border: 1px solid #afafaf;}
</style>
@endsection
@section('content')
    <div class="container-fluid h-100 p-0">
        <div class="row g-0 full-height-row">
            <div class="col-12 col-md-6 form-col">

                {{--<div class="lang-selector">
                    <select class="form-select lang-select" aria-label="Selecionar idioma" id="languageSelect">
                        <option value="pt" selected>ortuguês</option>
                        <option value="en">English</option>
                        <option value="es">Español</option>
                    </select>
                </div>--}}

                <div class="w-100 px-sm-3 px-md-4 py-3" style="max-width: 460px; margin: 0 auto;">

                    <!-- Logo / Marca -->
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                        <x-logo-psi4 :size="48" text="#1b323c" accent="#2fa595" />
                    </div>

                    <h2 class="fw-bold mt-3 mb-1 text-center" style="color: #1a3b44;">Bem-vindo(a) de volta</h2>
                    <p class="mb-4 text-center" style="font-size: 1rem;">Acesse sua conta com segurança e acolhimento.</p>

                    @if (request('code') === 'session-expired')
                        <div class="alert alert-warning" role="alert">{{ __('auth.session_expired') }}</div>
                    @endif

                    @if (session('social_error'))
                        <div class="alert alert-danger" role="alert">{{ session('social_error') }}</div>
                    @endif

                    <form id="form-login">

                        <div class="mb-3">
                            <label for="email" class="form-label fw-medium" style="color: #1f4e5c;">E-mail</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0" id="email-icon">
                                    <i class="fas fa-envelope icon-group"></i>
                                </span>
                                <input
                                    type="email"
                                    class="form-control border-start-0 ps-2"
                                    id="email"
                                    name="email"
                                    aria-label="E-mail"
                                    aria-describedby="email-icon"
                                    autocomplete="email"
                                    required
                                >
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-baseline">
                                <label for="password" class="form-label fw-medium" style="color: #1f4e5c;">Senha</label>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0" id="password-icon">
                                    <i class="fas fa-lock icon-group"></i>
                                </span>
                                <input
                                    type="password"
                                    class="form-control border-start-0 ps-2"
                                    id="password"
                                    name="password"
                                    aria-label="Senha"
                                    aria-describedby="password-icon"
                                    autocomplete="current-password"
                                    required
                                >
                                <button type="button" class="btn btn-outline-secondary bg-white border-start-0 btn-toggle-password" aria-label="Mostrar ou ocultar senha">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="lembrar" checked>
                                <label class="form-check-label user-select-none" for="lembrar" style="color: #2b5e64;">
                                    Lembrar-me
                                </label>
                            </div>
                            <a href="{{ route('password.request') }}" class="text-size-default" style="color: #2c7a7b; text-decoration: none; font-weight: 500;">
                                Esqueci minha senha
                            </a>
                        </div>

                        <button type="submit" class="btn btn-login w-100 py-3 mb-3">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Entrar na plataforma
                        </button>

                        @include('auth.partials.social-buttons', ['context' => 'login'])

                        <div class="text-center mt-4">
                            <span class="text-secondary-emphasis text-size-default" style="color: #486e78;">Ainda não tem acesso? </span>
                            <a href="{{ config('registration.invite_only') ? route('invite.request') : route('register') }}" class="fw-semibold text-size-default" style="color: #1f7a7a;">
                                {{ config('registration.invite_only') ? 'Peça seu convite' : 'Criar conta grátis' }} <i class="bi bi-arrow-right-short"></i>
                            </a>
                        </div>

                        <p class="text-center text-muted small mt-4 mb-0">
                            <i class="far fa-check-circle me-2"></i>Ambiente seguro · Criptografia ponta a ponta
                        </p>
                    </form>
                </div>
            </div>

            <div class="col-md-6 d-none d-md-block p-0 image-col">
                <img
                    src="{{ asset('imgs/others/psi-login-02.jpg') }}"
                    alt="Psicóloga sorrindo com confiança em um consultório acolhedor, em um ambiente reservado e tranquilo"
                    class="image-cover"
                    loading="lazy"
                >
            </div>
        </div>
    </div>
@endsection

@section('footer')
    <x-funnel-tag tag="login" />
@endsection
