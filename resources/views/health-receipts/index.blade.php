@extends('layouts.app')
@section('title', 'Receita Saúde')

@section('header')
    @include('components.css-dash')
    <style>
        .hr-intro { background: var(--dash-surface, #fff); border-radius: 12px; padding: 1rem 1.25rem; box-shadow: var(--dash-shadow); color: var(--dash-ink); }
        .hr-intro p { margin-bottom: .5rem; }
        .hr-intro small { color: var(--dash-muted); }
        .hr-steps { display: flex; flex-wrap: wrap; gap: .35rem; align-items: center; color: var(--dash-muted); font-size: .9rem; }
        .hr-steps i { font-size: .7rem; }
        .hr-steps a { color: inherit; font-weight: 600; text-decoration: underline; }
        .hr-alert { border-radius: 12px; padding: .85rem 1rem; display: flex; gap: .75rem; align-items: flex-start; margin-bottom: 1rem; }
        .hr-alert i { margin-top: .2rem; }
        .hr-alert-warning { background: #fff6e0; color: #7a5400; }
        .hr-alert-danger { background: #fdeaea; color: #a33030; }
        .hr-alert ul { margin: .35rem 0 0; padding-left: 1.1rem; }
        .hr-amount { font-size: 1.6rem; font-weight: 700; color: var(--dash-ink); line-height: 1; }
        .hr-meta { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .5rem; margin: .75rem 0 1rem; color: var(--dash-muted); }
        .hr-issue { display: inline-flex; align-items: center; gap: .3rem; color: #a33030; font-weight: 600; font-size: .9rem; }
        .hr-tabs .nav-link { border-radius: 999px; }
        .hr-pending-total { font-weight: 700; color: var(--dash-ink); }
    </style>
@endsection

@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            <i class="fas fa-file-invoice me-2"></i> Receita Saúde
        </h3>
    </div>

    <div class="hr-intro mb-4">
        <p>
            Gere o arquivo com os recibos dos seus atendimentos e importe no Carnê-Leão de uma vez, sem digitar recibo por recibo.
            <strong>Seus dados fiscais não saem do seu controle:</strong> o PSI4 não envia nada à Receita.
        </p>
        <div class="hr-steps mb-2">
            <a href="https://cav.receita.fazenda.gov.br/autenticacao/login" target="_blank" rel="noopener" title="Abrir o e-CAC da Receita Federal">e-CAC</a><i class="fas fa-chevron-right"></i>
            <span>Carnê-Leão</span><i class="fas fa-chevron-right"></i>
            <span>Escrituração</span><i class="fas fa-chevron-right"></i>
            <span>Importar Escrituração</span>
        </div>
        <small>Importe o arquivo direto, sem abrir no Excel (ele altera datas e CPFs). A escrituração é de sua responsabilidade; em caso de dúvida, fale com seu contador.</small>
        <div class="mt-3">
            <a href="https://cav.receita.fazenda.gov.br/autenticacao/login" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-arrow-up-right-from-square me-2"></i> Acessar o e-CAC
            </a>
        </div>
    </div>

    <div class="health-receipts-overview"></div>

    <ul class="nav nav-pills hr-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-receipts" data-bs-toggle="pill" data-bs-target="#pane-receipts" type="button" role="tab" aria-controls="pane-receipts" aria-selected="true">Recibos</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-batches" data-bs-toggle="pill" data-bs-target="#pane-batches" type="button" role="tab" aria-controls="pane-batches" aria-selected="false" onclick="paginateHealthReceiptBatches()">Arquivos gerados</button>
        </li>
    </ul>

    <div class="tab-content p-0">
        <div class="tab-pane fade show active" id="pane-receipts" role="tabpanel" aria-labelledby="tab-receipts">
            <div class="row">
                <div class="col-12 mb-4 col-sm-auto">
                    <button type="button" class="btn btn-primary w-100" onclick="openGenerateHealthReceiptBatch()">
                        <i class="fas fa-file-export me-2"></i> Gerar arquivo para o Carnê-Leão
                    </button>
                </div>

                <div class="col-12 mb-4 col-sm-auto">
                    <button type="button" class="btn btn-outline-primary w-100" onclick="openStoreHealthReceipt()">
                        <i class="fa-solid fa-plus me-2"></i> Registrar recebimento
                    </button>
                </div>

                <div class="col-6 mb-4 col-sm-auto">
                    @include('components.filter', ['css' => 'btn-outline-secondary'])
                </div>

                <div class="col-6 mb-4 col-sm-auto">
                    <button type="button" class="btn btn-outline-secondary w-100" onclick="paginateHealthReceipts()" title="Atualizar">
                        <i class="fas fa-sync icon-refresh"></i><span class="d-sm-none ms-2">Atualizar</span>
                    </button>
                </div>
            </div>

            <div class="dash-filter-chips mb-3" role="group" aria-label="Filtrar por status">
                @foreach(['' => 'Todos'] + \App\Enums\HealthReceiptStatusEnum::getTexts() as $value => $label)
                    <button type="button" class="dash-filter-chip" data-status="{{ $value }}" onclick="filterHealthReceiptsByStatus(this.dataset.status)">{{ $label }}</button>
                @endforeach
            </div>

            <div class="list-paginate">
                <div class="dash-skeleton"></div>
                <div class="dash-skeleton"></div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-batches" role="tabpanel" aria-labelledby="tab-batches">
            <div class="list-paginate-batches">
                <div class="dash-skeleton"></div>
            </div>
        </div>
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
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="patient_id">Paciente: </label>
                                    <select class="form-select" name="patient_id" id="patient_id"></select>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="filter_start_date">Pago de: </label>
                                    <input type="text" inputmode="numeric" name="start_date" id="filter_start_date" class="form-control date date_only">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="filter_end_date">Até: </label>
                                    <input type="text" inputmode="numeric" name="end_date" id="filter_end_date" class="form-control date date_only">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group">
                                    <label>Status: </label>
                                    <select name="status" class="form-select">
                                        <option value="">Todos</option>
                                        @foreach(\App\Enums\HealthReceiptStatusEnum::getTexts() as $value => $text)
                                            <option value="{{ $value }}">{{ $text }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="clearFiltersHealthReceipts()">Limpar</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="paginateHealthReceipts()">Buscar</button>
                </div>
            </div>
        </div>
    </div>

    @include('health-receipts.partials.receipt-modal')
    @include('health-receipts.partials.generate-modal')

@endsection

@section('footer')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initPatientFilter();
            initHealthReceiptsPage();
            paginateHealthReceipts();
        });
        document.getElementById('form-search').addEventListener('submit', function (e) {
            e.preventDefault();
            paginateHealthReceipts();
        });
    </script>
@endsection
