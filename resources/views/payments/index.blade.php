@extends('layouts.app')
@section('title', 'Pagamentos')

@section('header')
    @include('components.css-dash')
    <style>
        .pay-summary { grid-template-columns: 1fr; }
        @media (min-width: 768px) { .pay-summary { grid-template-columns: repeat(3, 1fr); } }
        .pay-tile { flex-direction: row; align-items: center; gap: 1rem; border: 0; text-align: left; width: 100%; }
        .pay-tile .dash-tile-icon { margin-bottom: 0; flex-shrink: 0; }
        .pay-tile.is-success .dash-tile-icon { background: #e4f6ec; color: #1c8a56; }
        .pay-tile.is-alert .dash-tile-icon { background: #fdeaea; color: #d64545; }
        .pay-tile.is-alert .pay-tile-value { color: #d64545; }
        .pay-tile.active { box-shadow: 0 0 0 2px var(--dash-primary), var(--dash-shadow); }
        .pay-tile-body { display: flex; flex-direction: column; min-width: 0; }
        .pay-tile-value { font-size: 1.8rem; font-weight: 700; line-height: 1.2; color: var(--dash-ink); }
        .pay-summary-skeleton { height: 96px; margin-bottom: 0; }
        .pay-card-amount { font-size: 1.8rem; font-weight: 700; color: var(--dash-ink); line-height: 1; }
        .pay-card-meta { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; margin: .75rem 0 1rem; color: var(--dash-muted); }
        .pay-due-muted { color: var(--dash-muted); }
        .pay-due-warning { color: #b27a00; font-weight: 600; }
        .pay-due-danger { color: #d64545; font-weight: 600; }
        .pay-due-success { color: #1c8a56; }
        .pay-methods i { font-size: 1.15rem; margin-left: .6rem; color: var(--dash-muted); }
        .pay-link-state { color: var(--dash-muted); font-size: .95rem; }
        .dash-round-btn.dash-round-btn-success .fa-whatsapp { font-size: 1.9rem; }
    </style>
@endsection

@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            @include('components.icon-payments', ['css' => 'me-2']) Pagamentos
        </h3>
    </div>

    @if($showIntro)
        @include('payments.partials.intro')
    @else
        @include('payments.partials.account-warning', ['paymentAccount' => $paymentAccount])

        <div class="row">
            <div class="col-12 mb-4 col-sm-auto">
                <a href="{{ route('payments.create') }}" class="btn btn-primary w-100">
                    <i class="fa-solid fa-plus me-2"></i> Nova cobrança
                </a>
            </div>

            <div class="col-6 mb-4 col-sm-auto">
                @include('components.filter', ['css' => 'btn-outline-secondary'])
            </div>

            <div class="col-6 mb-4 col-sm-auto">
                <button type="button" class="btn btn-outline-secondary w-100" onclick="paginatePaymentOrders()" title="Atualizar">
                    <i class="fas fa-sync icon-refresh"></i><span class="d-sm-none ms-2">Atualizar</span>
                </button>
            </div>
        </div>

        <div class="payments-summary">
            <div class="dash-grid pay-summary mb-4">
                <div class="dash-skeleton pay-summary-skeleton"></div>
                <div class="dash-skeleton pay-summary-skeleton"></div>
                <div class="dash-skeleton pay-summary-skeleton"></div>
            </div>
        </div>

        <div class="dash-filter-chips mb-3" role="group" aria-label="Filtrar por status">
            @foreach([
                '' => 'Todas',
                \App\Enums\PaymentOrderStatusEnum::PENDING->value => 'Pendentes',
                \App\Enums\PaymentOrderStatusEnum::PAID->value => 'Pagas',
                \App\Enums\PaymentOrderStatusEnum::EXPIRED->value => 'Vencidas',
                \App\Enums\PaymentOrderStatusEnum::REFUNDED->value => 'Reembolsadas',
            ] as $value => $label)
                <button type="button" class="dash-filter-chip" data-status="{{ $value }}" onclick="filterPaymentOrdersByStatus(this.dataset.status)">{{ $label }}</button>
            @endforeach
        </div>

        <div class="list-paginate">
            <div class="dash-skeleton"></div>
            <div class="dash-skeleton"></div>
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
                        <form id="form-search" autocomplete="off">
                            <div class="row">
                                <div class="col-lg-12 col-sm-12">
                                    <div class="form-group">
                                        <label for="patient_id">Paciente: </label>
                                        <select class="form-select" name="patient_id" id="patient_id"></select>
                                    </div>
                                </div>
                                <div class="col-lg-12 col-sm-12">
                                    <div class="form-group">
                                        <label>Status: </label>
                                        <select name="status" class="form-select">
                                            <option value="">Todos</option>
                                            @foreach(\App\Enums\PaymentOrderStatusEnum::getTexts() as $value => $text)
                                                <option value="{{ $value }}" @selected($value === request('status'))>{{ $text }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="clearFiltersPaymentOrders()">Limpar</button>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="paginatePaymentOrders()">Buscar</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

@endsection

@section('footer')
    <script>
        @unless($showIntro)
        document.addEventListener('DOMContentLoaded', function() {
            initPatientFilter();
            paginatePaymentOrders();
        });
        document.getElementById('form-search').addEventListener('submit', function (e) {
            e.preventDefault();
            paginatePaymentOrders();
        });
        @endunless
    </script>
@endsection
