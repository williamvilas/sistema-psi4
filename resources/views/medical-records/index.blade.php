@extends('layouts.app')
@section('title', 'Prontuários')

@section('header')
    @include('components.css-dash')
    <style>
        div:where(.swal2-container) div:where(.swal2-validation-message) {
            margin: 0 !important;
        }
        .mr-search { position: relative; }
        .mr-search > i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--dash-muted); pointer-events: none; }
        .mr-search .form-control { padding: 0 1rem 0 2.6rem; border-radius: 12px; height: 46px; }
        .mr-card-title { display: flex; align-items: center; gap: .5rem; margin-top: 1rem; color: var(--dash-ink); font-weight: 600; min-width: 0; }
        .mr-card-title i { color: var(--dash-muted); width: 1rem; text-align: center; flex-shrink: 0; }
        .mr-card-chips { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .75rem; }
    </style>
@endsection

@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            @include('components.icon-medical-records', ['css' => 'me-2']) Prontuários
        </h3>
    </div>

    @if(!empty($pendingTranscriptions))
        <div class="alert alert-info d-flex flex-wrap align-items-center gap-2" role="status">
            <i class="fas fa-microphone-lines" aria-hidden="true"></i>
            <span class="flex-grow-1">{{ trans_choice('session-transcriptions.pending_banner', $pendingTranscriptions, ['count' => $pendingTranscriptions]) }}</span>
            <a href="{{ route('session-transcriptions.index') }}" class="btn btn-primary btn-sm">Revisar</a>
        </div>
    @endif

    <form id="form-search" autocomplete="off">
        <div class="row g-3 align-items-center mb-3">
            <div class="col-12 col-sm-auto">
                <a href="{{ route('medical-records.create') }}" class="btn btn-primary w-100">
                    <i class="fa-solid fa-plus me-2"></i> Novo prontuário
                </a>
            </div>

            <div class="col-12 col-lg">
                <div class="mr-search">
                    <i class="fas fa-search"></i>
                    <input class="form-control" name="q" id="medical-records-search" type="search" maxlength="100" placeholder="Buscar por título" aria-label="Buscar prontuário por título">
                </div>
            </div>

            <div class="col-6 col-sm-auto">
                @include('components.filter', ['css' => 'btn-outline-secondary'])
            </div>

            <div class="col-6 col-sm-auto">
                <button type="button" class="btn btn-outline-secondary w-100" onclick="paginateMedicalRecords()" title="Atualizar">
                    <i class="fas fa-sync icon-refresh"></i><span class="d-sm-none ms-2">Atualizar</span>
                </button>
            </div>
        </div>

        <div class="dash-filter-chips mb-3" role="group" aria-label="Atalhos de período">
            <button type="button" class="dash-filter-chip" data-chip="all" onclick="clearFiltersMedicalRecords()">Todos</button>
            <button type="button" class="dash-filter-chip" data-chip="today" onclick="filterMedicalRecordsPeriod('today')">Hoje</button>
            <button type="button" class="dash-filter-chip" data-chip="last-7-days" onclick="filterMedicalRecordsPeriod('last-7-days')">Últimos 7 dias</button>
            <button type="button" class="dash-filter-chip" data-chip="month" onclick="filterMedicalRecordsPeriod('month')">Este mês</button>
        </div>

        <!-- Modal Filter -->
        <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="filterModalLabel">Filtrar</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-lg-12 col-sm-12">
                                <div class="form-group">
                                    <label for="patient_id">Paciente: </label>
                                    <select class="form-select" name="patient_id" id="patient_id"></select>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-12">
                                <div class="form-group">
                                    <label for="search_date">Data da consulta: </label>
                                    <input class="form-control date_range_current" name="date" id="search_date" type="text">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="clearFiltersMedicalRecords()">Limpar</button>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="paginateMedicalRecords()">Buscar</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="list-paginate">
        <div class="row">
            @for($s = 0; $s < 4; $s++)
                <div class="col-12 col-md-6 col-xl-4 col-xxl-3 mb-4"><div class="dash-skeleton mb-0" style="height: 190px;"></div></div>
            @endfor
        </div>
    </div>

@endsection

@section('footer')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initPatientFilter();
            initDateRangePickers(null, true);
            initMedicalRecordsSearch();
            paginateMedicalRecords();
        });
        document.getElementById('form-search').addEventListener('submit', function (e) {
            e.preventDefault();
            paginateMedicalRecords();
        });
    </script>
@endsection
