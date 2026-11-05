@extends('layouts.app-clean')
@section('title', 'Alterar Senha')

@section('header')
    @include('components.css-dash')
    @include('auth.partials.profile-css')
@endsection

@section('content')
    <div class="row justify-content-center prof-page mt-4">
        <div class="col-12 col-md-8 col-lg-6 col-xl-4">
            <div class="dash-card prof-card">
                <form id="form-force-change-password" class="prof-form" novalidate>
                    @csrf
                    @method('PUT')

                    <h4 class="dash-title"><i class="fa-solid fa-shield-halved"></i> Crie uma nova senha</h4>
                    <p class="prof-legend">Você entrou com uma senha temporária. Defina uma nova senha para continuar.</p>

                    @include('auth.partials.password-fields')

                    <div class="prof-actions">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fa-solid fa-check me-1"></i> Salvar e continuar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
