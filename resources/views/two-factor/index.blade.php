@extends('layouts.app')
@section('title', 'Autenticação de Dois Fatores')

@section('header')
    @include('components.css-dash')
    <style>
        .tf-hero { position: relative; overflow: hidden; padding: 2rem; }
        .tf-hero::after {
            content: ''; position: absolute; right: -60px; top: -60px; width: 220px; height: 220px; border-radius: 50%;
            background: radial-gradient(circle, rgba(31, 122, 128, .14), rgba(31, 122, 128, 0) 70%); pointer-events: none;
        }
        .tf-hero-icon {
            width: 64px; height: 64px; border-radius: 20px; background: var(--dash-primary-soft); color: var(--dash-primary);
            display: flex; align-items: center; justify-content: center; font-size: 1.9rem; flex-shrink: 0;
        }
        .tf-hero.is-on .tf-hero-icon { background: #e4f6ec; color: #1c8a56; }
        .tf-hero h4 { font-weight: 700; color: var(--dash-ink); margin: 0; font-size: 1.6rem; }
        .tf-hero p { color: var(--dash-muted); font-size: 1.2rem; line-height: 1.6; }
        .tf-status { font-size: 1rem; padding: .35rem .9rem; }

        .tf-benefits { display: grid; gap: .75rem; grid-template-columns: 1fr; margin: 0; padding: 0; list-style: none; }
        @media (min-width: 576px) { .tf-benefits { grid-template-columns: repeat(2, 1fr); } }
        .tf-benefit { display: flex; gap: .75rem; align-items: flex-start; padding: .85rem 1rem; border-radius: 16px; background: #f7f9fb; }
        .tf-benefit-icon {
            width: 36px; height: 36px; border-radius: 12px; background: #fff; color: var(--dash-primary);
            display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 1px 3px rgba(30, 42, 62, .06);
        }
        .tf-benefit strong { display: block; color: var(--dash-ink); font-size: 1.15rem; }
        .tf-benefit span { color: var(--dash-muted); font-size: 1.05rem; }

        .fa-solid, .fas {font-size: 1.6rem;}
        /* Assistente em etapas */
        .tf-stepper { display: flex; gap: .5rem; margin: 0 0 1.75rem; padding: 0; list-style: none; counter-reset: tf-step; }
        .tf-stepper li { flex: 1; min-width: 0; }
        .tf-stepper-bar { display: block; height: 6px; border-radius: 6px; background: var(--dash-line); margin-bottom: .5rem; transition: background .2s; }
        .tf-stepper-label { display: block; font-size: 1.2rem; font-weight: 600; color: var(--dash-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tf-stepper li.is-done .tf-stepper-bar, .tf-stepper li.is-current .tf-stepper-bar { background: var(--dash-primary); }
        .tf-stepper li.is-current .tf-stepper-label { color: var(--dash-ink); }
        .tf-step-title { font-size: 1.35rem; font-weight: 700; color: var(--dash-ink); margin-bottom: .35rem; }
        .tf-step-sub { color: var(--dash-muted); font-size: 1.15rem; margin-bottom: 1.25rem; }
        .tf-step-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; margin-top: 1.5rem; }
        .tf-btn { border-radius: 12px; font-weight: 600; padding: .65rem 1.25rem; }
        .tf-btn-link { background: none; border: 0; color: var(--dash-muted); font-weight: 600; padding: .65rem .5rem; }
        .tf-btn-link:hover { color: var(--dash-ink); }

        .tf-store { display: inline-flex; align-items: center; gap: .6rem; padding: .6rem 1.1rem; border-radius: 12px; background: #111; color: #fff; text-decoration: none; }
        .tf-store:hover { background: #333; color: #fff; }
        .tf-store i { font-size: 1.5rem; }
        .tf-store small { display: block; font-size: .7rem; opacity: .8; line-height: 1; }
        .tf-store strong { display: block; font-size: 1rem; line-height: 1.2; }

        .tf-scan { display: flex; flex-wrap: wrap; gap: 1.5rem; align-items: center; }
        .tf-qr-box {
            width: 200px; height: 200px; padding: 10px; border-radius: 16px; background: #fff; border: 1px solid var(--dash-line);
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .tf-qr-box svg { width: 100%; height: 100%; }
        .tf-scan-body { flex: 1; min-width: 220px; }
        .tf-scan-body ol { padding-left: 1.2rem; color: var(--dash-ink); font-size: 1.05rem; }
        .tf-scan-body ol li { margin-bottom: .35rem; }
        .tf-secret {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem;
            background: var(--dash-primary-soft); border-radius: 14px; padding: .6rem .6rem .6rem 1rem; margin-top: .5rem;
        }
        .tf-secret code { font-size: 1rem; font-weight: 700; color: var(--dash-primary); letter-spacing: .06em; word-break: break-all; }

        .tf-done-icon {
            width: 56px; height: 56px; border-radius: 50%; background: #e4f6ec; color: #1c8a56;
            display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 1rem;
        }
        .tf-recovery-alert { display: flex; gap: .6rem; align-items: flex-start; background: #fff4d9; color: #7a5300; border-radius: 14px; padding: .8rem 1rem; margin-bottom: 1rem; }
        .tf-recovery-alert i { margin-top: .2rem; }
        .tf-recovery-list {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: .5rem; padding: 1rem; margin: 0 0 1rem;
            list-style: none; border: 1px dashed #c9d4dd; border-radius: 14px; background: #fbfcfd;
        }
        .tf-recovery-list li {
            font-family: SFMono-Regular, Menlo, Consolas, monospace; font-size: 1.05rem; font-weight: 700; color: var(--dash-ink);
            text-align: center; padding: .35rem; border-radius: 8px; background: #fff;
        }

        /* Gerenciamento (2FA ativo) */
        .tf-manage .dash-row { padding: 1rem 0; flex-wrap: wrap; }
        .tf-row-icon {
            width: 44px; height: 44px; border-radius: 14px; background: var(--dash-primary-soft); color: var(--dash-primary);
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .tf-row-icon.is-danger { background: #fdeaea; color: #d64545; }
        .tf-row-name { display: block; font-size: 1.2rem; font-weight: 600; color: var(--dash-ink); }
        .tf-manage .dash-row-detail { font-size: 1rem; }
        .tf-btn-danger { border-radius: 12px; font-weight: 600; padding: .5rem .9rem; }

        .tf-aside { position: sticky; top: 1.5rem; }
        .tf-help .dash-row { align-items: flex-start; }
        .tf-help .dash-row-detail { font-size: 1.15rem; }

        /* Mockup do Google Authenticator */
        .tf-phone {
            width: 100%; max-width: 220px; aspect-ratio: 9 / 19; margin: 0 auto; padding: 7px;
            background: #000; border: 4px solid #4a4a4a; border-radius: 34px; position: relative;
            box-shadow: 0 18px 40px rgba(30, 42, 62, .18);
        }
        .tf-phone .fa-solid, .tf-phone .fas {font-size: 10px;}
        .tf-screen { height: 100%; background: #0d141b; border-radius: 26px; padding: 14px 12px; color: #e3e3e3; position: relative; overflow: hidden; }
        .tf-notch { position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 42%; height: 18px; background: #000; border-radius: 0 0 12px 12px; }
        .tf-status-bar { display: flex; justify-content: space-between; font-size: 10px; margin-bottom: 14px; }
        .tf-search { display: flex; align-items: center; gap: 8px; background: #1a2630; border-radius: 20px; padding: 6px 10px; font-size: 11px; color: #b8c2cc; }
        .tf-avatar { width: 20px; height: 20px; border-radius: 50%; background: #7e57c2; color: #fff; font-size: 10px; display: flex; align-items: center; justify-content: center; }
        .tf-account { margin-top: 18px; padding-bottom: 10px; border-bottom: 1px solid #2a3540; display: flex; justify-content: space-between; align-items: flex-end; }
        .tf-account-label { font-size: 11px; color: #e3e3e3; }
        .tf-blur { filter: blur(4px); user-select: none; }
        .tf-code { font-size: 26px; line-height: 1.2; color: #66c7f4; letter-spacing: 1px; }
        .tf-timer {
            width: 18px; height: 18px; border-radius: 50%;
            background: conic-gradient(#66c7f4 var(--tf-progress, 70%), transparent 0); animation: tf-countdown 10s linear infinite;
        }
        @property --tf-progress { syntax: '<percentage>'; inherits: false; initial-value: 100%; }
        @keyframes tf-countdown { 0%, 58% { --tf-progress: 100%; } 92%, 100% { --tf-progress: 0%; } }
        .tf-fab { position: absolute; right: 12px; bottom: 18px; width: 40px; height: 40px; border-radius: 12px; background: #1f2a34; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; }
        .tf-fab span { background: linear-gradient(90deg, #4285f4, #34a853, #fbbc05, #ea4335); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .tf-scene { position: absolute; inset: 0; border-radius: 26px; }
        .tf-scene-scan { background: #05080b; display: flex; flex-direction: column; align-items: center; justify-content: center; animation: tf-scene-scan 10s infinite; }
        .tf-scene-code { padding: 14px 12px; background: #0d141b; animation: tf-scene-code 10s infinite; }
        .tf-scan-hint { position: absolute; top: 44px; font-size: 11px; color: #e3e3e3; }
        .tf-viewfinder { position: relative; width: 62%; aspect-ratio: 1; padding: 8%; background: #fff; border-radius: 10px; animation: tf-qr-zoom 10s infinite; }
        .tf-qr { width: 100%; height: 100%; display: block; }
        .tf-corner { position: absolute; width: 22px; height: 22px; border: 3px solid #66c7f4; }
        .tf-corner-tl { top: -8px; left: -8px; border-right: 0; border-bottom: 0; border-top-left-radius: 8px; }
        .tf-corner-tr { top: -8px; right: -8px; border-left: 0; border-bottom: 0; border-top-right-radius: 8px; }
        .tf-corner-bl { bottom: -8px; left: -8px; border-right: 0; border-top: 0; border-bottom-left-radius: 8px; }
        .tf-corner-br { bottom: -8px; right: -8px; border-left: 0; border-top: 0; border-bottom-right-radius: 8px; }
        .tf-scan-line { position: absolute; left: 0; right: 0; height: 2px; background: #66c7f4; box-shadow: 0 0 10px 2px rgba(102, 199, 244, .8); animation: tf-scan-line 10s infinite; }
        .tf-scan-ok { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; border-radius: 10px; background: rgba(25, 135, 84, .85); color: #fff; font-size: 34px; opacity: 0; animation: tf-scan-ok 10s infinite; }
        @keyframes tf-scene-scan { 0%, 52% { opacity: 1; visibility: visible; } 58%, 96% { opacity: 0; visibility: hidden; } 100% { opacity: 1; visibility: visible; } }
        @keyframes tf-scene-code { 0%, 52% { opacity: 0; visibility: hidden; } 58%, 92% { opacity: 1; visibility: visible; } 98%, 100% { opacity: 0; visibility: hidden; } }
        @keyframes tf-qr-zoom { 0%, 8% { transform: scale(.85); } 20%, 46% { transform: scale(1); } 52%, 100% { transform: scale(1.08); } }
        @keyframes tf-scan-line { 0%, 14% { top: 0; opacity: 0; } 16% { top: 0; opacity: 1; } 30% { top: calc(100% - 2px); opacity: 1; } 44% { top: 0; opacity: 1; } 46%, 100% { top: 0; opacity: 0; } }
        @keyframes tf-scan-ok { 0%, 46% { opacity: 0; } 50%, 54% { opacity: 1; } 58%, 100% { opacity: 0; } }

        @media (max-width: 575.98px) {
            .tf-hero { padding: 1.5rem 1.25rem; }
            .tf-stepper li:not(.is-current) .tf-stepper-label { visibility: hidden; }
            .tf-stepper li.is-current .tf-stepper-label { overflow: visible; }
            .tf-qr-box { margin: 0 auto; }
            .tf-step-actions .btn { flex: 1; }
        }
        @media (prefers-reduced-motion: reduce) {
            .tf-timer, .tf-scene-scan, .tf-viewfinder, .tf-scan-line, .tf-scan-ok, .tf-scene-code { animation: none; }
            .tf-scene-scan { visibility: hidden; }
        }
    </style>
@endsection

@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            <i class="fas fa-shield-alt me-2 text-primary" aria-hidden="true"></i> Autenticação de Dois Fatores
        </h3>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-8 d-flex flex-column gap-4">

            <section class="dash-card tf-hero {{ $twoFactor['enabled'] ? 'is-on' : '' }}" aria-labelledby="tf-hero-title">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="tf-hero-icon" aria-hidden="true">
                        <i class="fa-solid {{ $twoFactor['enabled'] ? 'fa-shield-halved' : 'fa-mobile-screen-button' }}"></i>
                    </div>
                    <div>
                        <div class="dash-eyebrow">Segurança da conta</div>
                        <h4 id="tf-hero-title">
                            {{ $twoFactor['enabled'] ? 'Sua conta está protegida' : 'Proteja sua conta em 2 minutos' }}
                        </h4>
                    </div>
                </div>

                <p class="mb-3">
                    @if($twoFactor['enabled'])
                        Ao entrar na sua conta, pedimos o código do seu aplicativo autenticador além da senha. Para abrir os prontuários, basta a sua senha mestre.
                    @else
                        Além da senha, pedimos um código de 6 dígitos gerado no seu celular. Mesmo que alguém descubra sua senha, não conseguirá acessar os dados dos seus pacientes.
                    @endif
                </p>

                <span id="tf-status" role="status"
                      class="dash-chip tf-status {{ $twoFactor['enabled'] ? 'dash-chip-success' : 'dash-chip-warning' }}">
                    <i class="fa-solid {{ $twoFactor['enabled'] ? 'fa-check' : 'fa-triangle-exclamation' }}" aria-hidden="true"></i>
                    {{ $twoFactor['enabled'] ? 'Ativa' : 'Inativa' }}
                </span>

                @unless($twoFactor['enabled'])
                    <ul class="tf-benefits mt-4">
                        <li class="tf-benefit">
                            <span class="tf-benefit-icon" aria-hidden="true"><i class="fa-solid fa-user-shield"></i></span>
                            <div><strong>Protege seus pacientes</strong><span>Prontuários e dados sensíveis ficam seguros.</span></div>
                        </li>
                        <li class="tf-benefit">
                            <span class="tf-benefit-icon" aria-hidden="true"><i class="fa-solid fa-ban"></i></span>
                            <div><strong>Bloqueia invasores</strong><span>O código muda a cada 30 segundos.</span></div>
                        </li>
                        <li class="tf-benefit">
                            <span class="tf-benefit-icon" aria-hidden="true"><i class="fa-solid fa-scale-balanced"></i></span>
                            <div><strong>Ajuda a cumprir a LGPD</strong><span>Uma camada extra para dados pessoais.</span></div>
                        </li>
                        <li class="tf-benefit">
                            <span class="tf-benefit-icon" aria-hidden="true"><i class="fa-solid fa-bolt"></i></span>
                            <div><strong>Gratuito e rápido</strong><span>Configuração em menos de 2 minutos.</span></div>
                        </li>
                    </ul>
                @endunless
            </section>

            @if($twoFactor['enabled'])
                <section class="dash-card tf-manage" id="tf-manage" aria-labelledby="tf-manage-title">
                    <h4 class="dash-title" id="tf-manage-title"><i class="fa-solid fa-sliders"></i> Gerenciar</h4>

                    <div class="dash-row">
                        <span class="tf-row-icon" aria-hidden="true"><i class="fa-solid fa-mobile-screen-button"></i></span>
                        <div class="dash-row-body">
                            <span class="tf-row-name">Aplicativo autenticador</span>
                            <span class="dash-row-detail">
                                Configurado{{ $twoFactor['confirmed_at'] ? ' em '.$twoFactor['confirmed_at']->format('d/m/Y') : '' }}. Os códigos são gerados no Google Authenticator do seu celular.
                            </span>
                        </div>
                    </div>

                    <div class="dash-row">
                        <span class="tf-row-icon" aria-hidden="true"><i class="fa-solid fa-key"></i></span>
                        <div class="dash-row-body">
                            <span class="tf-row-name">
                                Códigos de recuperação
                                <span id="tf-codes-left" class="dash-chip {{ $twoFactor['recovery_codes_left'] > 2 ? 'dash-chip-muted' : 'dash-chip-warning' }}">
                                    {{ $twoFactor['recovery_codes_left'] }} {{ $twoFactor['recovery_codes_left'] === 1 ? 'restante' : 'restantes' }}
                                </span>
                            </span>
                            <span class="dash-row-detail">Use um deles para entrar se perder o celular. Gerar novos invalida os anteriores.</span>
                        </div>
                        <button type="button" class="dash-btn-soft" id="tf-regenerate">
                            <i class="fa-solid fa-rotate me-1" aria-hidden="true"></i> Gerar novos
                        </button>
                    </div>

                    <div class="d-none pt-3" id="tf-regenerated">
                        @include('two-factor.partials.recovery-codes')
                    </div>

                    <div class="dash-row">
                        <span class="tf-row-icon is-danger" aria-hidden="true"><i class="fa-solid fa-power-off"></i></span>
                        <div class="dash-row-body">
                            <span class="tf-row-name">Desativar autenticação de 2 fatores</span>
                            <span class="dash-row-detail">Sua conta volta a ser protegida apenas pela senha. Não recomendado.</span>
                        </div>
                        <button type="button" class="btn btn-outline-danger tf-btn-danger" id="tf-disable">Desativar</button>
                    </div>
                </section>
            @else
                <section class="dash-card" id="tf-wizard" aria-labelledby="tf-wizard-title">
                    <h4 class="dash-title" id="tf-wizard-title"><i class="fa-solid fa-list-check"></i> Ativar agora</h4>

                    <ol class="tf-stepper" aria-label="Etapas da ativação">
                        <li class="is-current" data-tf-indicator="1"><span class="tf-stepper-bar"></span><span class="tf-stepper-label">1. Baixar app</span></li>
                        <li data-tf-indicator="2"><span class="tf-stepper-bar"></span><span class="tf-stepper-label">2. Escanear</span></li>
                        <li data-tf-indicator="3"><span class="tf-stepper-bar"></span><span class="tf-stepper-label">3. Confirmar</span></li>
                        <li data-tf-indicator="4"><span class="tf-stepper-bar"></span><span class="tf-stepper-label">4. Recuperação</span></li>
                    </ol>

                    <div data-tf-step="1">
                        <div class="tf-step-title">Instale o Google Authenticator</div>
                        <p class="tf-step-sub">É gratuito e funciona em Android e iPhone. Se já tem o aplicativo, é só continuar.</p>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2"
                               target="_blank" rel="noopener noreferrer" class="tf-store">
                                <i class="fab fa-google-play" aria-hidden="true"></i>
                                <span><small>Disponível no</small><strong>Google Play</strong></span>
                            </a>
                            <a href="https://apps.apple.com/app/google-authenticator/id388497605"
                               target="_blank" rel="noopener noreferrer" class="tf-store">
                                <i class="fab fa-apple" aria-hidden="true"></i>
                                <span><small>Baixar na</small><strong>App Store</strong></span>
                            </a>
                        </div>
                        <div class="tf-step-actions">
                            <button type="button" class="btn btn-primary tf-btn" id="tf-start">
                                Já tenho o app, continuar <i class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div data-tf-step="2" class="d-none">
                        <div class="tf-step-title">Escaneie o QR Code</div>
                        <p class="tf-step-sub">Abra o Google Authenticator no celular e aponte a câmera para o código abaixo.</p>
                        <div class="tf-scan">
                            <div class="tf-qr-box" id="tf-qr" aria-label="QR Code para configurar o aplicativo autenticador" role="img"></div>
                            <div class="tf-scan-body">
                                <ol>
                                    <li>Toque no botão <strong>+</strong> do aplicativo.</li>
                                    <li>Escolha <strong>Ler código QR</strong>.</li>
                                    <li>Aponte a câmera para o QR Code.</li>
                                </ol>
                                <details>
                                    <summary class="fw-semibold">Não consegue escanear?</summary>
                                    <p class="text-muted small mt-2 mb-1">No aplicativo, escolha <strong>Inserir chave de configuração</strong> e digite a chave:</p>
                                    <div class="tf-secret">
                                        <code id="tf-secret"></code>
                                        <button type="button" class="dash-btn-soft" id="tf-copy-secret">
                                            <i class="fa-regular fa-copy me-1" aria-hidden="true"></i> Copiar
                                        </button>
                                    </div>
                                </details>
                            </div>
                        </div>
                        <div class="tf-step-actions">
                            <button type="button" class="tf-btn-link" data-tf-go="1">
                                <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i> Voltar
                            </button>
                            <button type="button" class="btn btn-primary tf-btn" data-tf-go="3">
                                Já escaneei, continuar <i class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div data-tf-step="3" class="d-none">
                        <div class="tf-step-title">Digite o código do aplicativo</div>
                        <p class="tf-step-sub">Informe os 6 dígitos que aparecem para <strong>PSI4</strong> no Google Authenticator.</p>
                        <x-otp-input id="tf-otp" />
                        <div class="tf-step-actions">
                            <button type="button" class="tf-btn-link" data-tf-go="2">
                                <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i> Voltar
                            </button>
                            <button type="button" class="btn btn-primary tf-btn" id="tf-confirm" disabled>
                                <i class="fa-solid fa-lock me-1" aria-hidden="true"></i> Ativar proteção
                            </button>
                        </div>
                    </div>

                    <div data-tf-step="4" class="d-none">
                        <div class="tf-done-icon" aria-hidden="true"><i class="fa-solid fa-check"></i></div>
                        <div class="tf-step-title">Pronto! Falta só guardar seus códigos</div>
                        <p class="tf-step-sub">Se perder o celular, use um destes códigos no lugar do código do aplicativo.</p>
                        @include('two-factor.partials.recovery-codes')
                    </div>
                </section>
            @endif
        </div>

        <div class="col-12 col-xl-4">
            <div class="tf-aside d-flex flex-column gap-4">
                <div class="d-none d-xl-block">
                    @include('two-factor.partials.phone-mockup')
                </div>

                <section class="dash-card tf-help" aria-labelledby="tf-help-title">
                    <h4 class="dash-title" id="tf-help-title"><i class="fa-solid fa-life-ring"></i> Perdeu o celular?</h4>
                    <div class="dash-row">
                        <span class="tf-row-icon" aria-hidden="true"><i class="fa-solid fa-key"></i></span>
                        <div class="dash-row-body">
                            <span class="tf-row-name">Código de recuperação</span>
                            <span class="dash-row-detail">Na tela de login, use um dos códigos que você guardou.</span>
                        </div>
                    </div>
                    <div class="dash-row">
                        <span class="tf-row-icon" aria-hidden="true"><i class="fa-regular fa-envelope"></i></span>
                        <div class="dash-row-body">
                            <span class="tf-row-name">Link por e-mail</span>
                            <span class="dash-row-detail">Sem os códigos, peça um link de desativação para o seu e-mail na tela de login.</span>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

@endsection
