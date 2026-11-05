@extends('layouts.app')
@section('title', 'Alterar Senha')

@section('header')
    @include('components.css-dash')
    @include('auth.partials.profile-css')
@endsection

@section('content')
    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            <i class="fa-solid fa-key me-2"></i> Alterar Senha
        </h3>
    </div>

    <div class="row justify-content-center prof-page">
        <div class="col-12 col-md-8 col-lg-6 col-xl-5">
            <div class="dash-card prof-card">
                <form id="form-update-password" class="prof-form" novalidate>
                    @csrf
                    @method('PUT')

                    <h4 class="dash-title"><i class="fa-solid fa-shield-halved"></i> Nova senha de acesso</h4>
                    <p class="prof-legend">Por segurança, você continuará conectado só neste dispositivo.</p>

                    @include('auth.partials.password-fields', ['withCurrent' => true])

                    <div class="prof-actions">
                        <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary">Voltar ao perfil</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-check me-1"></i> Alterar senha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
