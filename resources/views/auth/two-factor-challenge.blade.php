@extends('layouts.guest')
@section('title', 'Verificação em 2 fatores')
@section('content')
    <style>
        .tf-wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; }
        .tf-card { width: 100%; max-width: 440px; }
        .tf-card .logo-img { width: 88px; height: 88px; object-fit: cover; border-radius: 50%; }
        .tf-card .login-title { font-size: 1.5rem; font-weight: 600; }
        .tf-card .splash-description { font-size: 1rem; line-height: 1.5; margin-bottom: 0; }
        .tf-card .form-label { font-size: 1rem; font-weight: 500; }
        .tf-card .tf-recovery-code { font-size: 1.35rem; letter-spacing: .15rem; min-height: 56px; }
        .tf-card .btn-lg { font-size: 1.05rem; min-height: 48px; }
        .tf-card .tf-links a { font-size: 1rem; display: inline-block; padding: 6px 0; }
        @media (max-width: 575.98px) {
            .tf-wrapper { align-items: flex-start; padding: 12px; }
            .tf-card .login-title { font-size: 1.3rem; }
            .tf-card .tf-recovery-code { font-size: 1.2rem; }
            .tf-card .card-header, .tf-card .card-body { padding-left: 1rem; padding-right: 1rem; }
        }
    </style>
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
                    <h2 class="login-title">Verificação em 2 fatores</h2>
                    <p class="splash-description" id="two-factor-description">
                        Informe o código de 6 dígitos exibido no Google Authenticator.
                    </p>
                </div>
                <div class="card-body">
                    <form id="form-two-factor-challenge" autocomplete="off">
                        <div class="login-form">
                            <div class="form-group" id="two-factor-otp-group">
                                <span class="form-label d-block text-center">Código de verificação</span>
                                <x-otp-input id="two-factor-otp" label="Código de verificação de 6 dígitos" class="justify-content-center" />
                            </div>
                            <div class="form-group d-none" id="two-factor-recovery-group">
                                <label for="two-factor-recovery-code" class="form-label">Código de recuperação</label>
                                <input class="form-control text-center tf-recovery-code" id="two-factor-recovery-code" type="text"
                                       autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                                       data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other"
                                       maxlength="11" placeholder="xxxxx-xxxxx">
                            </div>
                            <input type="hidden" id="code-value" name="code">
                            <div class="form-group row login-submit mt-4">
                                <div class="col-12">
                                    <button class="btn btn-primary btn-lg w-100" type="submit">Verificar</button>
                                </div>
                            </div>
                            <div class="text-center mt-3 tf-links">
                                <a href="#" id="btn-use-recovery-code">Usar um código de recuperação</a>
                                <a href="#" id="btn-use-app-code" class="d-none">Usar o código do aplicativo</a>
                            </div>
                            <div class="text-center tf-links">
                                <a href="#" id="btn-send-two-factor-reset">Perdi o acesso ao aplicativo</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
