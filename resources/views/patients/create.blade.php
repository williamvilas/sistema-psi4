@extends('layouts.app')
@section('title', 'Cadastrar Paciente')

@section('header')
    @include('components.css-dash')
    @include('patients.css')
@endsection

@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            @include('components.icon-patient', ['css' => 'me-2']) Cadastrar Paciente
        </h3>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-7">
            <nav class="pat-breadcrumb" aria-label="Navegação">
                <a href="{{ route('patients.index') }}">Pacientes</a>
                <i class="fas fa-chevron-right"></i>
                <span>Novo paciente</span>
            </nav>

            <div class="dash-card pat-form-card">
                @include('patients.form', [
                    'formId' => 'form-store-patients',
                    'cancelUrl' => route('patients.index'),
                ])
            </div>
        </div>
    </div>

@endsection

@section('footer')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initPatientForm('#form-store-patients');
        });
    </script>
@endsection
