@extends('layouts.app')
@section('title', 'Atividade de Acesso')

@section('header')
    @include('components.css-dash')
    @include('access-logs._styles')
@endsection

@section('content')
    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            <i class="fas fa-clock-rotate-left me-2"></i> Atividade de acesso
        </h3>
    </div>

    <p class="text-muted mb-3">
        Registro de quem entrou na conta e de cada vez que um prontuário foi criado, aberto, editado ou apagado, com data, IP e dispositivo.
        Se encontrar um acesso que não reconhece, troque sua senha e a senha mestre.
    </p>

    <form id="form-search" autocomplete="off">
        @if($medicalRecordId)
            <input type="hidden" name="medical_record_id" id="medical_record_id" value="{{ $medicalRecordId }}">
        @endif

        <div class="row g-3 align-items-center mb-3">
            <div class="col-12 col-md-5">
                <select class="form-select" name="action" id="action" aria-label="Filtrar por tipo de acesso" onchange="paginateAccessLogs()">
                    <option value="">Todas as atividades</option>
                    @foreach($actions as $action)
                        <option value="{{ $action->value }}">{{ $action->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4">
                <input class="form-control date_range_current" name="date" id="search_date" type="text" placeholder="Período" aria-label="Período">
            </div>
            <div class="col-6 col-md-auto">
                <button type="button" class="btn btn-outline-secondary w-100" onclick="clearFiltersAccessLogs()">Limpar</button>
            </div>
            <div class="col-6 col-md-auto">
                <button type="button" class="btn btn-outline-secondary w-100" onclick="paginateAccessLogs()" title="Atualizar">
                    <i class="fas fa-sync icon-refresh"></i><span class="d-md-none ms-2">Atualizar</span>
                </button>
            </div>
        </div>

        @if($medicalRecordId)
            <div class="dash-filter-chips mb-3" id="access-logs-record-filter">
                <span class="dash-filter-chip active">Somente este prontuário</span>
                <a href="{{ route('access-logs.index') }}" class="dash-filter-chip">Ver toda a conta</a>
            </div>
        @endif
    </form>

    <div class="list-paginate">
        <div class="dash-skeleton" style="height: 320px;"></div>
    </div>
@endsection

@section('footer')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initDateRangePickers(() => paginateAccessLogs(), true);
            paginateAccessLogs();
        });
        document.getElementById('form-search').addEventListener('submit', function (e) {
            e.preventDefault();
            paginateAccessLogs();
        });
    </script>
@endsection
