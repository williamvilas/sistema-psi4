{{-- Editor de prontuário estilo documento (compartilhado entre criar e editar). --}}
@php($isEdit = !empty($record))
@php($review = $transcription ?? null)
@php($formId = $review ? 'form-review-session-transcription' : ($isEdit ? 'form-update-medical-records' : 'form-store-medical-records'))

<form id="{{ $formId }}" class="doc-form d-none" autocomplete="off">
    @if($isEdit)
        <input type="hidden" id="inputId" value="{{ encrypt($record->id) }}">
    @endif

    <div class="doc-topbar">
        <a href="{{ $review ? route('session-transcriptions.index') : route('medical-records.index') }}" class="doc-back" title="Voltar" aria-label="Voltar">
            <i class="fas fa-arrow-left"></i>
        </a>

        <div class="doc-title-wrap">
            <input type="text" name="title" id="title" class="doc-title" maxlength="255"
                   value="{{ $review ? config('session_transcription.record_title') : ($isEdit ? $record->title : '') }}" placeholder="Título (ex. Sessão 11)" aria-label="Título do prontuário" @readonly($review) required/>
            <div class="doc-status-line">
                <div class="doc-status" id="docStatus" aria-live="polite">
                    <i class="fas fa-check-circle"></i> <span>Sem alterações</span>
                </div>
                <span class="doc-title-hint" id="docTitleHint" tabindex="0"
                      title="O conteúdo é criptografado no seu navegador. O título não: evite dados clínicos nele.">
                    <i class="fas fa-lock"></i> Criptografado
                </span>
            </div>
        </div>

        @if($isEdit)
            <button type="button" class="btn btn-outline-secondary btn-sm" id="docAccessToggle" aria-controls="docAccessPanel" aria-expanded="false" title="Quem acessou este prontuário">
                <i class="fas fa-clock-rotate-left"></i><span class="d-none d-md-inline ms-1">Acessos</span>
            </button>
        @endif
        @if($review)
            {{-- Sem o assistente de IA: ele enviaria o rascunho ao provedor em claro. --}}
            <button type="button" class="btn btn-outline-danger btn-sm js-reject-session-transcription"
                    data-uuid="{{ $review->uuid }}" data-patient="{{ $review->patient->name }}" data-date="{{ $review->occurred_on->format('d/m/Y') }}">
                <i class="fas fa-trash me-1"></i> Recusar
            </button>
            <button type="submit" class="btn btn-primary btn-sm doc-save" title="Só o texto como está na tela entra no prontuário">
                <i class="fas fa-check me-1"></i> Aprovar e salvar no prontuário
            </button>
        @else
            <button type="button" class="btn btn-outline-secondary btn-sm doc-ia-toggle" id="docIaToggle" aria-controls="docIaPanel" aria-expanded="false" title="Assistente de IA (Ctrl+Shift+I)">
                <i class="fas fa-robot me-1"></i> IA
            </button>
            <button type="submit" class="btn btn-primary btn-sm doc-save" title="Salvar (Ctrl+S)">
                <i class="fas fa-save me-1"></i> Salvar
            </button>
        @endif
    </div>

    @if($review)
        <div class="alert alert-warning mx-3 mt-3 mb-0" role="note">
            <strong>Rascunho gerado por IA.</strong> Confira e edite antes de aprovar. A transcrição automática pode errar nomes e expressões, e o resumo pode omitir ou distorcer o que foi dito.
            Só o texto do editor entra no prontuário: a transcrição, abaixo, é apagada junto com o rascunho.
            Se não decidir até {{ $review->review_deadline->timezone(\App\Helpers\UserHelper::getTimezone())->format('d/m/Y') }}, tudo será apagado.
        </div>
        {{-- Preenchida no navegador depois de decifrar. Fica fora do editor e não é salva. --}}
        <details class="mx-3 mt-3 d-none" id="sessionTranscriptPanel">
            <summary>Ver a transcrição da sessão</summary>
            <div class="small mt-2" id="sessionTranscriptBody"></div>
        </details>
    @endif

    <div class="doc-meta">
        <div class="doc-meta-item doc-meta-patient">
            <label for="patient_id">Paciente <span class="text-danger">*</span></label>
            @if($review)
                <input type="text" id="patient_id" class="form-control form-control-sm" value="{{ $review->patient->name }}" readonly>
            @else
                <select class="js-example-data-ajax form-select form-select-sm" name="patient_id" id="patient_id" required>
                    @if($isEdit)
                        <option value="{{ encrypt($record->patient->id) }}" selected>{{ $record->patient->name }} - {{ formatPhone($record->patient->phone) }}</option>
                    @endif
                </select>
            @endif
        </div>

        <div class="doc-meta-item">
            <label for="date_of_consultation">Data da sessão <span class="text-danger">*</span></label>
            <input type="tel" name="date_of_consultation" id="date_of_consultation" class="form-control date_only"
                   value="{{ $review ? $review->occurred_on->format('d/m/Y') : ($isEdit ? formatDate($record->date_of_consultation, 'd/m/Y') : \App\Helpers\UserHelper::getDateCurrent()->format('d/m/Y')) }}" @readonly($review) required/>
        </div>
    </div>

    <div class="doc-toolbar" id="docToolbar" role="toolbar" aria-label="Formatação">
        <div class="doc-tb-group">
            <button type="button" data-cmd="undo" title="Desfazer (Ctrl+Z)" aria-label="Desfazer"><i class="fas fa-undo"></i></button>
            <button type="button" data-cmd="redo" title="Refazer (Ctrl+Y)" aria-label="Refazer"><i class="fas fa-redo"></i></button>
        </div>

        <div class="doc-tb-group">
            <select id="tbBlock" title="Estilo do parágrafo" aria-label="Estilo do parágrafo">
                <option value="p">Texto normal</option>
                <option value="h1">Título 1</option>
                <option value="h2">Título 2</option>
                <option value="h3">Título 3</option>
                <option value="blockquote">Citação</option>
            </select>
            <select id="tbFont" title="Fonte" aria-label="Fonte">
                <option value="">Fonte padrão</option>
                <option value="Arial">Arial</option>
                <option value="Georgia">Georgia</option>
                <option value="Times New Roman">Times New Roman</option>
                <option value="Verdana">Verdana</option>
                <option value="Tahoma">Tahoma</option>
                <option value="Courier New">Courier New</option>
            </select>
            <select id="tbSize" title="Tamanho da fonte" aria-label="Tamanho da fonte">
                <option value="">14</option>
                @foreach([10, 11, 12, 16, 18, 20, 24, 28, 36] as $size)
                    <option value="{{ $size }}px">{{ $size }}</option>
                @endforeach
            </select>
        </div>

        <div class="doc-tb-group">
            <button type="button" data-cmd="bold" title="Negrito (Ctrl+B)" aria-label="Negrito"><i class="fas fa-bold"></i></button>
            <button type="button" data-cmd="italic" title="Itálico (Ctrl+I)" aria-label="Itálico"><i class="fas fa-italic"></i></button>
            <button type="button" data-cmd="underline" title="Sublinhado (Ctrl+U)" aria-label="Sublinhado"><i class="fas fa-underline"></i></button>
            <button type="button" data-cmd="strike" title="Tachado" aria-label="Tachado"><i class="fas fa-strikethrough"></i></button>
            <label class="doc-tb-color" title="Cor do texto"><i class="fas fa-font"></i><input type="color" id="tbColor" value="#202124" aria-label="Cor do texto"></label>
            <label class="doc-tb-color" title="Cor de destaque"><i class="fas fa-highlighter"></i><input type="color" id="tbHighlight" value="#fff176" aria-label="Cor de destaque"></label>
            <button type="button" data-cmd="link" title="Inserir link (Ctrl+K)" aria-label="Inserir link"><i class="fas fa-link"></i></button>
            <button type="button" data-cmd="clear" title="Limpar formatação" aria-label="Limpar formatação"><i class="fas fa-eraser"></i></button>
        </div>

        <div class="doc-tb-group">
            <button type="button" data-cmd="alignLeft" title="Alinhar à esquerda" aria-label="Alinhar à esquerda"><i class="fas fa-align-left"></i></button>
            <button type="button" data-cmd="alignCenter" title="Centralizar" aria-label="Centralizar"><i class="fas fa-align-center"></i></button>
            <button type="button" data-cmd="alignRight" title="Alinhar à direita" aria-label="Alinhar à direita"><i class="fas fa-align-right"></i></button>
            <button type="button" data-cmd="alignJustify" title="Justificar" aria-label="Justificar"><i class="fas fa-align-justify"></i></button>
            <select id="tbLineHeight" title="Espaçamento entre linhas" aria-label="Espaçamento entre linhas">
                <option value="">Espaçamento</option>
                <option value="1">Simples</option>
                <option value="1.15">1,15</option>
                <option value="1.5">1,5</option>
                <option value="2">Duplo</option>
            </select>
        </div>

        <div class="doc-tb-group">
            <button type="button" data-cmd="bulletList" title="Lista com marcadores" aria-label="Lista com marcadores"><i class="fas fa-list-ul"></i></button>
            <button type="button" data-cmd="orderedList" title="Lista numerada" aria-label="Lista numerada"><i class="fas fa-list-ol"></i></button>
            <button type="button" data-cmd="taskList" title="Lista de verificação" aria-label="Lista de verificação"><i class="fas fa-tasks"></i></button>
            <button type="button" data-cmd="outdent" title="Diminuir recuo" aria-label="Diminuir recuo"><i class="fas fa-outdent"></i></button>
            <button type="button" data-cmd="indent" title="Aumentar recuo" aria-label="Aumentar recuo"><i class="fas fa-indent"></i></button>
        </div>

        <div class="doc-tb-group">
            <button type="button" data-cmd="table" title="Inserir tabela 3x3" aria-label="Inserir tabela"><i class="fas fa-table"></i></button>
            <button type="button" data-cmd="addRow" class="doc-tb-table" title="Adicionar linha" aria-label="Adicionar linha"><i class="fas fa-plus"></i> L</button>
            <button type="button" data-cmd="addColumn" class="doc-tb-table" title="Adicionar coluna" aria-label="Adicionar coluna"><i class="fas fa-plus"></i> C</button>
            <button type="button" data-cmd="deleteRow" class="doc-tb-table" title="Remover linha" aria-label="Remover linha"><i class="fas fa-minus"></i> L</button>
            <button type="button" data-cmd="deleteColumn" class="doc-tb-table" title="Remover coluna" aria-label="Remover coluna"><i class="fas fa-minus"></i> C</button>
            <button type="button" data-cmd="deleteTable" class="doc-tb-table" title="Remover tabela" aria-label="Remover tabela"><i class="fas fa-trash"></i></button>
        </div>
    </div>

    <div class="doc-canvas">
        <div id="editor"></div>
        <div class="invalid-feedback invalid-feedback-notes"></div>
    </div>
</form>

@unless($review)
<aside class="doc-side-panel doc-ia-panel d-none" id="docIaPanel" aria-label="Assistente de IA">
    <div class="doc-side-panel-resizer" role="separator" aria-orientation="vertical" title="Arraste para redimensionar"></div>
    @include('components.ia-chat')
</aside>
@endunless

@if($isEdit)
    @include('access-logs._panel', ['medicalRecordId' => encrypt($record->id)])
@endif

@include('components.icon-loading', ['css' => 'doc-loading'])
