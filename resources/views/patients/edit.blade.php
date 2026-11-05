@extends('layouts.app')
@section('title', 'Editar Paciente')

@section('header')
    @include('components.css-dash')
    @include('patients.css')
@endsection

@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            @include('components.icon-patient', ['css' => 'me-2']) Editar Paciente
        </h3>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-7">

            @if(!empty($patient))
                @php($showUrl = route('patients.show', ['id' => encrypt($patient->id)]))

                <nav class="pat-breadcrumb" aria-label="Navegação">
                    <a href="{{ route('patients.index') }}">Pacientes</a>
                    <i class="fas fa-chevron-right"></i>
                    <a href="{{ $showUrl }}">{{ $patient->name }}</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>Editar</span>
                </nav>

                <div class="dash-card pat-form-card">
                    <div class="pat-form-head">
                        @include('components.avatar-initials', ['name' => $patient->name])
                        <div class="pat-form-head-body">
                            <div class="pat-form-head-name" title="{{ $patient->name }}">{{ $patient->name }}</div>
                            @if($patient->created_at)
                                <div class="pat-form-head-sub">Cadastrado em {{ formatDate($patient->created_at, 'd/m/Y') }}</div>
                            @endif
                        </div>
                    </div>

                    @include('patients.form', [
                        'formId' => 'form-update-patients',
                        'cancelUrl' => $showUrl,
                    ])
                </div>
            @else
                @include('components.unauthorized')
                <p class="text-center sub-alert-p">Essa restrição garante a confidencialidade e a segurança das informações.</p>
            @endif
        </div>
    </div>

@endsection

@section('footer')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initPatientForm('#form-update-patients');
        });
    </script>
@endsection
