@extends('layouts.app')
@section('title', 'Documentos')

@section('header')
    @include('components.css-dash')
    <style>
        .doc-search { position: relative; }
        .doc-search > i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--dash-muted); pointer-events: none; }
        .doc-search .form-control { padding: 0 1rem 0 2.6rem; border-radius: 12px; height: 46px; }
        .doc-card { transition: box-shadow .2s, transform .15s; }
        .doc-card:hover { transform: translateY(-3px); }
        .doc-card .dash-item-name { white-space: normal; }
        .doc-card-icon { width: 52px; height: 52px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
        .doc-card-desc { color: var(--dash-muted); font-size: 1.10rem; line-height: 1.5; margin: 1rem 0 0; }
        .doc-card .dash-item-actions { margin-top: auto; }
        .doc-card > :nth-last-child(2) { margin-bottom: 1rem; }
        .doc-card-chips { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .75rem; }

        /* Modal de emissão */
        .doc-modal-header { background: var(--dash-surface); border-bottom: 1px solid var(--dash-line); padding: .75rem 1.25rem; }
        .doc-modal-header .doc-card-icon { width: 42px; height: 42px; border-radius: 12px; font-size: 1.1rem; }
        .doc-modal-header .modal-title { font-weight: 700; color: var(--dash-ink); }
        .doc-modal-header small { color: var(--dash-muted); }
        .doc-steps { counter-reset: doc-step; }
        .doc-step { display: flex; font-size: 1.15rem; align-items: center; gap: .6rem; font-weight: 700; color: var(--dash-ink); }
        .doc-step::before {
            counter-increment: doc-step; content: counter(doc-step);
            width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--dash-primary-soft); color: var(--dash-primary); font-size: .9rem;
        }
        .doc-step-hint { color: var(--dash-muted); font-size: 1.10rem; margin: .15rem 0 0 calc(28px + .6rem); }
        .doc-preview-wrap { position: relative; height: 100%; }
        .doc-preview-placeholder {
            position: absolute; inset: 0; z-index: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-align: center; padding: 2rem; background: rgba(245, 247, 250, .96); color: var(--dash-muted);
        }
        .doc-preview-placeholder i { font-size: 2.6rem; color: #b8c9cc; margin-bottom: 1rem; }
        .doc-preview-placeholder strong { color: var(--dash-ink); font-size: 1.15rem; }
        @media (max-width: 767.98px) {
            .doc-preview-wrap { min-height: 70vh; }
        }
    </style>
@endsection

@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            @include('components.icon-documents', ['css' => 'me-2']) Documentos
        </h3>
    </div>

    <form id="form-search" autocomplete="off">
        <div class="row g-3 align-items-center mb-3">
            <div class="col-12 col-lg-6 col-xl-5">
                <div class="doc-search">
                    <i class="fas fa-search"></i>
                    <input class="form-control" name="q" id="documents-search" type="search" maxlength="100" placeholder="Buscar modelo de documento" aria-label="Buscar modelo de documento">
                </div>
            </div>
        </div>

        <input type="hidden" name="type" id="documents-type">

        <div class="dash-filter-chips mb-3" role="group" aria-label="Filtrar por tipo de documento">
            <button type="button" class="dash-filter-chip active" data-chip="" onclick="filterDocumentsByType('')">Todos</button>
            @foreach(\App\Enums\DocumentsEnum::getTexts() as $k => $v)
                <button type="button" class="dash-filter-chip" data-chip="{{ $k }}" onclick="filterDocumentsByType(@js($k))">
                    <i class="{{ \App\Enums\DocumentsEnum::from($k)->icon() }} me-1"></i> {{ $v }}
                </button>
            @endforeach
        </div>
    </form>

    <div class="list-paginate">
        <div class="row">
            @for($s = 0; $s < 4; $s++)
                <div class="col-12 col-md-6 col-xl-4 col-xxl-3 mb-4"><div class="dash-skeleton mb-0" style="height: 230px;"></div></div>
            @endfor
        </div>
    </div>

    <!-- Modal Preview -->
    <div class="modal" id="previewDocumentModal" tabindex="-1" aria-labelledby="previewDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content modal-transparent">
                <div class="modal-header doc-modal-header">
                    <div class="d-flex align-items-center gap-3 min-w-0">
                        <div class="doc-card-icon dash-tone-0"><i id="previewDocumentModalIcon" class="far fa-file-alt"></i></div>
                        <div class="min-w-0">
                            <h5 class="modal-title text-truncate mb-0" id="previewDocumentModalLabel">Emitir documento</h5>
                            <small>Preencha os dados à esquerda e confira a prévia antes de emitir.</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="container-fluid">
                        <div class="row g-0">
                            <div class="col-12 col-md-4">
                                <div class="card document-form-card">
                                    <div class="card-body">
                                        <form id="form-search-patients-by-documents" autocomplete="off">
                                            <div class="row g-3 doc-steps">
                                                <div class="col-md-12 col-12">
                                                    <div class="doc-step">Paciente</div>
                                                    <p class="doc-step-hint">Quem vai receber o documento.</p>
                                                </div>
                                                <div class="col-md-12 col-12 mt-2">
                                                    <div class="form-group">
                                                        <label for="patient_id" class="visually-hidden">Paciente</label>
                                                        <select class="js-example-data-ajax form-select" name="patient_id" id="patient_id" required></select>
                                                        <input type="hidden" name="patient_name" id="patient_name">
                                                        <input type="hidden" name="document_id" id="document_id">
                                                        <div id="agenda-selected-inputs"></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-12 col-12 mt-4 doc-step-fields d-none">
                                                    <div class="doc-step">Informações do documento</div>
                                                    <p class="doc-step-hint">Use "Salvar como padrão" para reaproveitar o texto nas próximas emissões.</p>
                                                </div>
                                                <div id="document-fields" class="col-12"><div class="row g-3"></div></div>
                                                <div class="col-md-12 col-12 mt-4 field-patient-doc-preview field-agenda-doc d-none">
                                                    <div class="doc-step">Sessões realizadas</div>
                                                    <p class="doc-step-hint">Marque as sessões que devem constar no documento.</p>
                                                </div>
                                                <div class="col-md-12 col-12 field-patient-doc-preview field-agenda-doc d-none">
                                                    <div class="form-group">
                                                        <div class="border rounded p-3 bg-light-subtle">
                                                            <div id="agenda-selected-count" class="fw-semibold">0 Agendas Selecionadas</div>
                                                            <small id="agenda-selected-dates" class="text-muted">Nenhuma agenda selecionada</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 col-12 field-patient-doc-preview field-agenda-doc d-none">
                                                    <div class="form-group">
                                                        <label>De:</label>
                                                        <input type="tel" name="start" id="agenda-date-from" class="form-control date date_only">
                                                    </div>
                                                </div>
                                                <div class="col-md-6 col-12 field-patient-doc-preview field-agenda-doc d-none">
                                                    <div class="form-group">
                                                        <label>Até:</label>
                                                        <input type="tel" name="end" id="agenda-date-to" class="form-control date date_only">
                                                    </div>
                                                </div>
                                                <div class="col-md-12 col-12 mt-2 field-patient-doc-preview field-agenda-doc d-none">
                                                    <div class="form-group">
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <span class="fw-semibold">Seleções</span>
                                                            <button type="button" class="btn btn-link btn-sm p-0 text-secondary" onclick="clearAgendaSelection()">Limpar</button>
                                                        </div>
                                                        <div id="agenda-selection-list" class="agenda-selection-list" style="max-height: 560px; overflow-y: auto;"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-8">
                                <div class="doc-preview-wrap">
                                    <div id="document-preview-placeholder" class="doc-preview-placeholder">
                                        <i class="far fa-file-alt"></i>
                                        <strong>Selecione um paciente para ver a prévia</strong>
                                        <span>O documento é atualizado aqui conforme você preenche os campos.</span>
                                    </div>
                                    <iframe id="iframe-preview" class="iframe-app" src="" title="Prévia do documento"></iframe>
                                </div>
                                <form id="form-iframe-preview">
                                    <input type="hidden" name="id" value="" />
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer modal-dark justify-content-center">
                    <button type="button" class="btn btn-primary" id="btn-emit-document" onclick="emitDocument()" disabled title="Selecione um paciente para emitir">
                        <i class="fas fa-file-pdf me-2"></i> Emitir Documento
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('footer')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initDocumentsSearch();
            paginateDocuments();
            initOnlyDatePickers(function () {
                getAgendaPatientByDocuments($('#patient_id').val(), 1);
            });
            $('.js-example-data-ajax').select2({
                language: 'pt-BR',
                placeholder: 'Pesquisar paciente...',
                minimumInputLength: 2,
                dropdownParent: $('#previewDocumentModal'),
                ajax: {
                    url: '/patients/autocomplete',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.map(function (item) {
                                return {
                                    id: item.id,
                                    text: item.name + ' - ' + formatWhatsApp(item.phone),
                                    patient_name: item.name,
                                    phone: item.phone
                                };
                            })
                        };
                    }
                }
            }).on('select2:select', function (e) {
                const selected = e.params.data;
                $('#patient_id').val(selected.id);
                $('#patient_name').val(selected.patient_name);
                onPatientSelectedByDocuments(selected.id);
            });
        });

        document.getElementById('form-search').addEventListener('submit', function (e) {
            e.preventDefault();
            paginateDocuments();
        });
    </script>
@endsection

