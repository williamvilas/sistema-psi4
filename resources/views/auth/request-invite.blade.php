@extends('layouts.guest-fullpage')
@section('title', __('invitations.title'))
@section('content')
    <div class="container-fluid h-100 p-0">
        <div class="row g-0 full-height-row">
            <div class="col-12 col-md-6 form-col">

                <div class="w-100 px-sm-3 px-md-4 py-3" style="max-width: 460px; margin: auto;">

                    <!-- Logo / Marca -->
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                        <x-logo-psi4 :size="48" text="#1b323c" accent="#2fa595" />
                    </div>

                    <div id="request-invite-intro">
                        <h2 class="fw-bold mt-3 mb-1 text-center" style="color: #1a3b44;">{{ __('invitations.title') }}</h2>
                        <p class="mb-4 text-center" style="font-size: 1.10rem;">{{ __('invitations.subtitle') }}</p>
                    </div>

                    <form id="form-request-invite">

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
                            <label for="whatsapp" class="form-label fw-medium" style="color: #1f4e5c;">WhatsApp</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0" id="whatsapp-icon">
                                    <i class="fab fa-whatsapp icon-group"></i>
                                </span>
                                <input
                                    type="tel"
                                    class="form-control border-start-0 ps-2 whatsapp"
                                    id="whatsapp"
                                    name="whatsapp"
                                    inputmode="tel"
                                    placeholder="(00) 00000-0000"
                                    aria-label="WhatsApp"
                                    aria-describedby="whatsapp-icon"
                                    autocomplete="tel-national"
                                    required
                                >
                            </div>
                        </div>

                        {{-- Honeypot: escondido de quem usa a tela; robôs costumam preencher. --}}
                        <div style="position: absolute; left: -9999px;" aria-hidden="true">
                            <label for="website">Não preencha</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="consent" name="consent" value="1" required>
                            <label class="form-check-label small" for="consent" style="color: #486e78;">
                                {{ __('invitations.consent') }}
                                Saiba mais na <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener" style="color: #1f7a7a;">Política de Privacidade</a>.
                            </label>
                        </div>

                        <button type="submit" class="btn btn-login w-100 py-3 mb-3 btn-request-invite">
                            <i class="bi bi-send me-2"></i>{{ __('invitations.button') }}
                        </button>

                        <div class="text-center mt-4">
                            <span class="text-secondary-emphasis text-size-default" style="color: #486e78;">Já tem uma conta? </span>
                            <a href="{{ route('login') }}" class="fw-semibold text-size-default" style="color: #1f7a7a;">
                                Faça login aqui <i class="bi bi-arrow-right-short"></i>
                            </a>
                        </div>
                    </form>

                    <div id="request-invite-success" class="text-center mt-4 d-none" role="status">
                        <i class="fa-regular fa-circle-check mb-3" style="font-size: 2.5rem; color: #2fa595;"></i>
                        <h3 class="fw-bold" style="color: #1a3b44;">{{ __('invitations.success_title') }}</h3>
                        <p style="font-size: 1rem;">{{ __('invitations.requested') }}</p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 d-none d-md-block p-0 image-col">
                <img
                    src="{{ asset('imgs/others/psi-celular.jpg') }}"
                    alt="Psicóloga sorrindo ao ler uma mensagem no celular, sentada numa poltrona em um consultório acolhedor com plantas e livros"
                    class="image-cover"
                >
            </div>
        </div>
    </div>
@endsection

@section('footer')
    <x-funnel-tag tag="request_invite" />
@endsection
