@extends('layouts.guest-fullpage')
@section('title', 'Recuperar Senha')

@section('content')
    <div class="container-fluid h-100 p-0">
        <div class="row g-0 full-height-row">
            <div class="col-12 col-md-6 form-col">

                <div class="w-100 px-sm-3 px-md-4 py-3" style="max-width: 460px; margin: 0 auto;">

                    <a href="{{ route('login') }}" class="d-inline-block mb-3 py-1" style="font-size: 1rem;">
                        <i class="fa-solid fa-arrow-left"></i>
                        Voltar
                    </a>

                    <!-- Logo / Marca -->
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                        <x-logo-psi4 :size="48" text="#1b323c" accent="#2fa595" />
                    </div>

                    <h2 class="fw-bold mt-3 mb-1 text-center" style="color: #1a3b44;">Esqueceu sua senha?</h2>
                    <p class="mb-4 text-center" style="font-size: 1rem;">
                        Informe o e-mail da sua conta e enviaremos um link para você criar uma nova senha.
                    </p>

                    <form id="form-forgot-password">
                        @csrf

                        <div class="mb-4">
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
                                    inputmode="email"
                                    placeholder="seuemail@exemplo.com"
                                    aria-label="E-mail"
                                    aria-describedby="email-icon"
                                    autocomplete="username"
                                    autofocus
                                    required
                                >
                            </div>
                        </div>

                        <button type="submit" class="btn btn-login w-100 py-3 mb-3 btn-forgot-password">
                            <i class="bi bi-envelope-arrow-up me-2"></i>Enviar link
                        </button>

                        <div class="text-center mt-4">
                            <a href="{{ route('login') }}" class="fw-semibold text-size-default" style="color: #1f7a7a;">
                                Lembrei minha senha
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
                    src="{{ asset('imgs/others/psi-senha.jpg') }}"
                    alt="Sessão de terapia acolhedora — psicóloga e paciente em ambiente tranquilo, tons suaves"
                    class="image-cover"
                    loading="lazy"
                >
            </div>
        </div>
    </div>
@endsection

@section('footer')
    <x-funnel-tag tag="forgot_password" />
@endsection
