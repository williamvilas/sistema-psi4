@extends('layouts.guest-fullpage')
@section('title', 'Testar Grátis por 14 Dias')
@section('content')
    <div class="container-fluid h-100 p-0">
        <div class="row g-0 full-height-row">
            <div class="col-12 col-md-6 form-col">

                <div class="w-100 px-sm-3 px-md-4 py-3" style="max-width: 460px; margin: auto;">

                     <!-- Logo / Marca -->
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                        <x-logo-psi4 :size="48" text="#1b323c" accent="#2fa595" />
                    </div>

                    <h2 class="fw-bold mt-3 mb-1 text-center" style="color: #1a3b44;">Crie sua conta</h2>
                    <p class="mb-4 text-center" style="font-size: 1rem;">Comece agora gratuitamente.</p>

                    @if (session('social_error'))
                        <div class="alert alert-danger" role="alert">{{ session('social_error') }}</div>
                    @endif

                    <form id="form-register">

                        <div class="mb-3">
                            <label for="name" class="form-label fw-medium" style="color: #1f4e5c;">Nome completo</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0" id="name-icon">
                                    <i class="fas fa-user icon-group"></i>
                                </span>
                                <input
                                    type="text"
                                    class="form-control border-start-0 ps-2 text-capitalize"
                                    id="name"
                                    name="name"
                                    aria-label="Nome completo"
                                    aria-describedby="name-icon"
                                    autocomplete="name"
                                    required
                                >
                            </div>
                        </div>

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
                            <label for="password" class="form-label fw-medium" style="color: #1f4e5c;">Senha</label>
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
                                    autocomplete="new-password"
                                    required
                                >
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label fw-medium" style="color: #1f4e5c;">Confirmar senha</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0" id="password-confirmation-icon">
                                    <i class="fas fa-lock icon-group"></i>
                                </span>
                                <input
                                    type="password"
                                    class="form-control border-start-0 ps-2"
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    aria-label="Confirmar senha"
                                    aria-describedby="password-confirmation-icon"
                                    autocomplete="new-password"
                                    required
                                >
                            </div>
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="terms" name="terms" value="1" required>
                            <label class="form-check-label small" for="terms" style="color: #486e78;">
                                Li e concordo com os
                                <a href="{{ route('terms-service') }}" target="_blank" rel="noopener" style="color: #1f7a7a;">Termos de Uso</a>
                                e a
                                <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener" style="color: #1f7a7a;">Política de Privacidade</a>.
                            </label>
                        </div>

                        <button type="submit" class="btn btn-login w-100 py-3 mb-3">
                            <i class="bi bi-person-plus me-2"></i>Criar minha conta gratuita
                        </button>

                        @include('auth.partials.social-buttons', ['context' => 'register', 'showTerms' => true])

                        <div class="text-center mt-4">
                            <span class="text-secondary-emphasis text-size-default" style="color: #486e78;">Já tem uma conta? </span>
                            <a href="{{ route('login') }}" class="fw-semibold text-size-default" style="color: #1f7a7a;">
                                Faça login aqui <i class="bi bi-arrow-right-short"></i>
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
                    src="{{ asset('imgs/others/psi-register.jpg') }}"
                    alt="Psicóloga sorridente em consultório acolhedor, com plantas e livros ao fundo"
                    class="image-cover"
                    loading="lazy"
                >
            </div>
        </div>
    </div>
@endsection

@section('footer')
    <x-funnel-tag tag="register" />
@endsection
