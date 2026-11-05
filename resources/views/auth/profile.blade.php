@extends('layouts.app')
@section('title', 'Meu Perfil')

@section('header')
    @include('components.css-dash')
    @include('auth.partials.profile-css')
@endsection

@section('content')
    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            <i class="fa-regular fa-circle-user me-2"></i> Meu Perfil
        </h3>
    </div>

    <div class="row justify-content-center prof-page">
        <div class="col-12 col-lg-9 col-xl-7">

            <div class="dash-card prof-card prof-head">
                @if($photo)
                    <img src="{{ $photo }}" alt="Foto de perfil" class="prof-photo">
                @else
                    @include('components.avatar-initials', ['name' => $user->name])
                @endif

                <div class="prof-head-body">
                    <h4 class="prof-head-name" id="profile-head-name" title="{{ $user->name }}">{{ $user->name }}</h4>
                    <div class="prof-head-sub">
                        <span id="profile-head-email">{{ $user->email }}</span>
                    </div>
                    <div class="prof-head-chips">
                        <span class="dash-chip dash-chip-info">{!! getIconPlanByUser($currentPlan) !!} Plano {{ __('plans.'.$currentPlan) }}</span>
                        <span class="dash-chip dash-chip-muted">
                            <i class="fa-regular fa-calendar"></i> Desde {{ $user->created_at->locale('pt_BR')->translatedFormat('M/Y') }}
                        </span>
                    </div>
                </div>

                <a href="{{ route('settings.index') }}#psicologo" class="dash-btn-soft prof-head-link" title="A foto é alterada em Configurações › Psicólogo">
                    <i class="fa-solid fa-camera me-1"></i> {{ $photo ? 'Alterar foto' : 'Adicionar foto' }}
                </a>
            </div>

            <div class="dash-card prof-card">
                <form id="form-profile-user" class="prof-form" autocomplete="on" novalidate data-profile-form>
                    @csrf
                    @method('PUT')

                    <h4 class="dash-title"><i class="fa-regular fa-id-card"></i> Dados pessoais</h4>
                    <p class="prof-legend"><span class="text-danger">*</span> Campos obrigatórios</p>

                    <div class="row">
                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label for="name">Nome completo <span class="text-danger">*</span></label>
                                <div class="prof-input">
                                    <i class="fa-regular fa-user"></i>
                                    <input id="name" type="text" name="name" class="form-control text-capitalize" maxlength="255"
                                           autocomplete="name" value="{{ $user->name }}" required>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label for="document">CPF <span class="text-danger">*</span></label>
                                <div class="prof-input {{ $user->document ? 'is-locked' : '' }}">
                                    <i class="fa-regular fa-address-card"></i>
                                    <input id="document" type="tel" name="document" class="form-control cpf" inputmode="numeric"
                                           placeholder="___.___.___-__" value="{{ maskValue($user->document, '###.###.###-##') }}"
                                           @if($user->document) readonly aria-describedby="document-hint" @endif required>
                                    @if($user->document)
                                        <i class="fa-solid fa-lock prof-input-lock" aria-hidden="true"></i>
                                    @endif
                                </div>
                                @if($user->document)
                                    <small class="prof-hint" id="document-hint">
                                        O CPF não pode ser alterado.
                                        <a href="{{ route('help.index') }}" class="prof-hint-link">Fale com o suporte</a>
                                    </small>
                                @endif
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label for="email">E-mail <span class="text-danger">*</span></label>
                                <div class="prof-input">
                                    <i class="fa-regular fa-envelope"></i>
                                    <input id="email" type="email" name="email" class="form-control" maxlength="255"
                                           autocomplete="email" value="{{ $user->email }}" required>
                                </div>
                                <small class="prof-hint">Usado para entrar no sistema e receber avisos.</small>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label for="whatsapp">WhatsApp <span class="text-danger">*</span></label>
                                <div class="prof-input">
                                    <i class="fab fa-whatsapp"></i>
                                    <input id="whatsapp" type="tel" name="whatsapp" class="form-control whatsapp" inputmode="tel"
                                           autocomplete="tel-national" placeholder="(__) _____-____"
                                           value="{{ $user->whatsapp ? formatPhone($user->whatsapp) : '' }}" required>
                                </div>
                                <small class="prof-hint">Usado pelo suporte para falar com você.</small>
                            </div>
                        </div>
                    </div>

                    <div class="prof-actions">
                        <span class="prof-dirty-hint d-none" id="profile-dirty-hint">
                            <i class="fa-solid fa-circle-exclamation"></i> Alterações não salvas
                        </span>
                        <button type="button" class="btn btn-outline-secondary d-none" id="btn-reset-profile">Desfazer</button>
                        <button type="submit" class="btn btn-primary" id="btn-save-profile" disabled>
                            <i class="fa-regular fa-floppy-disk me-1"></i> Salvar alterações
                        </button>
                    </div>
                </form>
            </div>

            <div class="dash-card prof-card" id="profile-security">
                <h4 class="dash-title"><i class="fa-solid fa-shield-halved"></i> Segurança</h4>

                <div class="dash-row prof-security-row">
                    <span class="prof-security-icon"><i class="fa-solid fa-key"></i></span>
                    <div class="dash-row-body">
                        <span class="prof-security-name">Senha</span>
                        <span class="prof-security-detail">Troque sua senha de acesso periodicamente.</span>
                    </div>
                    <a href="{{ route('profile.change-password') }}" class="dash-btn-soft">Alterar senha</a>
                </div>

                <div class="dash-row prof-security-row">
                    <span class="prof-security-icon"><i class="fa-solid fa-mobile-screen-button"></i></span>
                    <div class="dash-row-body">
                        <span class="prof-security-name">
                            Autenticação de dois fatores
                            @if($twoFactorEnabled)
                                <span class="dash-chip dash-chip-success" id="profile-2fa-status"><i class="fa-solid fa-check"></i> Ativa</span>
                            @else
                                <span class="dash-chip dash-chip-warning" id="profile-2fa-status"><i class="fa-solid fa-triangle-exclamation"></i> Inativa</span>
                            @endif
                        </span>
                        <span class="prof-security-detail">Proteção extra com um código do celular ao entrar.</span>
                    </div>
                    <a href="{{ route('two-factor.index') }}" class="dash-btn-soft">{{ $twoFactorEnabled ? 'Gerenciar' : 'Ativar' }}</a>
                </div>
            </div>

        </div>
    </div>
@endsection
