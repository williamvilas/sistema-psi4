@extends('layouts.app')
@section('title', 'Pacientes')

@section('header')
    @include('components.css-dash')
    <style>
        .pat-search { position: relative; }
        .pat-search > i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--dash-muted); pointer-events: none; }
        .pat-search .form-control { padding: 0 1rem 0 2.6rem; border-radius: 12px; height: 46px; }
        .pat-card-info { font-size: 1.3rem; display: flex; align-items: center; gap: .5rem; margin-top: 1rem; color: var(--dash-ink); }
        .pat-card-info i { color: var(--dash-muted); width: 1rem; text-align: center; }
        .pat-card-chips { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .75rem; }
        .dash-item.is-birthday { box-shadow: 0 0 0 2px #1c8a56, var(--dash-shadow); }
        .dash-item .dash-round-btn-success .fa-whatsapp { font-size: 1.9rem; }
    </style>
@endsection

@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            @include('components.icon-patient', ['css' => 'me-2']) Pacientes
        </h3>
    </div>

    <form id="form-search" autocomplete="off">
        <div class="row g-3 align-items-center mb-3">
            <div class="col-12 col-sm-auto">
                <a href="{{ route('patients.create') }}" class="btn btn-primary w-100">
                    <i class="fa-solid fa-plus me-2"></i> Novo paciente
                </a>
            </div>

            <div class="col-12 col-lg">
                <div class="pat-search">
                    <i class="fas fa-search"></i>
                    <input class="form-control" name="q" id="patients-search" type="search" maxlength="100" placeholder="Buscar por nome, CPF ou WhatsApp" aria-label="Buscar paciente">
                </div>
            </div>

            <div class="col-6 col-sm-auto">
                @include('components.filter', ['css' => 'btn-outline-secondary'])
            </div>

            <div class="col-6 col-sm-auto">
                <button type="button" class="btn btn-outline-secondary w-100" onclick="paginatePatients()" title="Atualizar">
                    <i class="fas fa-sync icon-refresh"></i><span class="d-sm-none ms-2">Atualizar</span>
                </button>
            </div>
        </div>

        <div class="dash-filter-chips mb-3" role="group" aria-label="Atalhos de filtro">
            <button type="button" class="dash-filter-chip" data-chip="all" onclick="clearFiltersPatients()">Todos</button>
            <button type="button" class="dash-filter-chip" data-chip="birthday-day" onclick="filterPatientsBirthdayDay()">
                <i class="fas fa-birthday-cake me-1"></i> Aniversariantes do dia
            </button>
            <button type="button" class="dash-filter-chip" data-chip="birthday-month" onclick="filterPatientsBirthdayMonth()">
                <i class="fas fa-birthday-cake me-1"></i> Aniversariantes do mês
            </button>
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
                            @php($birthday = request()->query('birthday_celebrants'))
                            <div class="col-lg-12 col-sm-12">
                                <div class="form-group">
                                    <label for="birthday_celebrants">Aniversariantes: </label>
                                    <input class="form-control date_range_current" name="birthday_celebrants" id="birthday_celebrants" type="tel" value="{{ $birthday }}">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="clearFiltersPatients()">Limpar</button>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="paginatePatients()">Buscar</button>
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
            initDateRangePickers();
            initPatientsSearch();
            paginatePatients();
        });
        document.getElementById('form-search').addEventListener('submit', function (e) {
            e.preventDefault();
            paginatePatients();
        });
    </script>
@endsection
