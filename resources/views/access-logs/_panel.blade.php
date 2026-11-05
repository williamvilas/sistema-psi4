{{-- Painel lateral "Acessos" do prontuário (visualizar e editar). $medicalRecordId: id criptografado. --}}
@include('access-logs._styles')

<aside class="doc-side-panel doc-access-panel d-none" id="docAccessPanel" aria-label="Acessos deste prontuário">
    <div class="doc-side-panel-resizer" role="separator" aria-orientation="vertical" title="Arraste para redimensionar"></div>

    <div class="doc-side-panel-header">
        <strong class="doc-side-panel-title"><i class="fas fa-clock-rotate-left me-1"></i> Acessos deste prontuário</strong>
        <div class="d-flex gap-1">
            <button type="button" class="doc-side-panel-btn" id="docAccessRefresh" title="Atualizar" aria-label="Atualizar">
                <i class="fas fa-sync icon-refresh"></i>
            </button>
            <button type="button" class="doc-side-panel-btn" id="docAccessClose" title="Fechar" aria-label="Fechar acessos">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <form id="form-search-access-logs-panel" class="doc-access-filters" autocomplete="off">
        <input type="hidden" name="medical_record_id" value="{{ $medicalRecordId }}">
        <input type="hidden" name="panel" value="1">

        <select class="form-select form-select-sm" name="action" id="docAccessAction" aria-label="Filtrar por tipo de atividade">
            <option value="">Todas as atividades</option>
            @foreach(\App\Enums\AccessLogActionEnum::medicalRecordCases() as $action)
                <option value="{{ $action->value }}">{{ $action->label() }}</option>
            @endforeach
        </select>
        <div class="d-flex gap-2">
            <input class="form-control date_range_current" name="date" id="docAccessDate" type="text"
                   placeholder="Período" aria-label="Período" data-opens="left">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="docAccessClear">Limpar</button>
        </div>
    </form>

    <div class="doc-access-list" id="docAccessList" aria-live="polite"></div>

    <div class="doc-access-footer">
        <a href="{{ route('access-logs.index') }}">Ver atividade de toda a conta <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
</aside>
