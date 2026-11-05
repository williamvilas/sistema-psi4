let selectedAgendaValues = [];
let selectedAgendaLabels = {};
let documentHasAgenda = false;
let previewTimer = null;

$(document).on('change', '.agenda-check', function () {
    const value = String($(this).val());

    if ($(this).is(':checked')) {
        if (!selectedAgendaValues.includes(value)) {
            selectedAgendaValues.push(value);
        }
        selectedAgendaLabels[value] = String($(this).data('label'));
    } else {
        selectedAgendaValues = selectedAgendaValues.filter(function (item) {
            return item !== value;
        });
        delete selectedAgendaLabels[value];
    }

    syncSelectedAgendaInputs();
    sendDataByDocuments(getDocumentIdByDoc());
});

function syncSelectedAgendaInputs() {
    const container = $('#agenda-selected-inputs').empty();

    selectedAgendaValues.forEach(function (value) {
        $('<input>', { type: 'hidden', name: 'agenda_check[]', value: value }).appendTo(container);
    });

    updateAgendaSelectionSummary();
}

function updateAgendaSelectionSummary() {
    const total = selectedAgendaValues.length;
    const dates = selectedAgendaValues
        .slice()
        .sort()
        .map(function (value) {
            return selectedAgendaLabels[value];
        });

    $('#agenda-selected-count').text(`${total} ${total === 1 ? 'Agenda Selecionada' : 'Agendas Selecionadas'}`);
    $('#agenda-selected-dates').text(total ? dates.join(', ') : 'Nenhuma agenda selecionada');
}

function restoreAgendaChecks() {
    $('#agenda-selection-list .agenda-check').each(function () {
        $(this).prop('checked', selectedAgendaValues.includes(String($(this).val())));
    });
}

function resetAgendaSelection() {
    selectedAgendaValues = [];
    selectedAgendaLabels = {};
    syncSelectedAgendaInputs();
}

function getDocumentIdByDoc() {
    return $('#document_id').val()
}

let paginateDocumentsSeq = 0;
let searchDocumentsTimer = null;
let documentTitle = '';

function paginateDocuments(callback = null, loading = true) {
    const form = '#form-search';
    const seq = ++paginateDocumentsSeq;
    clearTimeout(searchDocumentsTimer);
    syncDocumentsChips();

    sendRequest(
        '/documents/paginate',
        'GET',
        form,
        'json',
        function(response) {
            // ignora respostas de buscas antigas que chegaram depois da mais recente
            if (seq !== paginateDocumentsSeq) {
                return;
            }
            $('.list-paginate').html(response.html);
            if (isFunction(callback)) {
                callback();
            }
        },
        loading
    );
}

function initDocumentsSearch() {
    const selector = '#documents-search';

    if (!elementExists(selector)) {
        return;
    }

    // busca sem o loading em tela cheia, para não tirar o foco do campo enquanto digita
    $(selector).on('input', function () {
        clearTimeout(searchDocumentsTimer);
        searchDocumentsTimer = setTimeout(function () {
            paginateDocuments(null, false);
        }, 400);
    });
}

function filterDocumentsByType(type) {
    $('#documents-type').val(type);
    paginateDocuments();
}

function syncDocumentsChips() {
    if (!elementExists('.dash-filter-chip')) {
        return;
    }

    const type = $('#documents-type').val() || '';

    $('.dash-filter-chip').removeClass('active');
    $('.dash-filter-chip').filter(function () {
        return String($(this).data('chip')) === type;
    }).addClass('active');
}

function clearDocumentFields() {
    documentHasAgenda = false;
    $('#document-fields > .row').empty();
    $('.doc-step-fields').addClass('d-none');
    $('.field-agenda-doc').addClass('d-none');
}

function togglePreviewPlaceholder(show) {
    $('#document-preview-placeholder').toggleClass('d-none', !show);
}

function toggleEmitDocument(enabled) {
    $('#btn-emit-document')
        .prop('disabled', !enabled)
        .attr('title', enabled ? 'Gerar o PDF do documento' : 'Selecione um paciente para emitir');
}

function slugifyDocumentName(text) {
    return String(text || '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function getDocumentFileName() {
    const name = [documentTitle, $('#patient_name').val()]
        .map(slugifyDocumentName)
        .filter(Boolean)
        .join('-');

    return (name || 'documento') + '.pdf';
}

function searchPatientByDocuments() {
    $('#patient_name').val('');
    $('#document_id').val('');
    $('.js-example-data-ajax').val(null).trigger('change');
    $('#agenda-date-from, #agenda-date-to').val('');
    $('#agenda-selection-list').empty();
    resetAgendaSelection();
    $('.invalid-feedback').html('');
}

function cleanPreviewDocument() {
    $('.field-patient-doc-preview').addClass('d-none');
    clearDocumentFields();
    searchPatientByDocuments();
    togglePreviewPlaceholder(true);
    toggleEmitDocument(false);
}

function carregarPreview(id) {
    var parent = $('#iframe-preview').parent();
    var iframeId = 'iframe-preview';

    // Remove o iframe
    $('#iframe-preview').remove();

    // Recria o iframe
    var novoIframe = $(`<iframe id="${iframeId}" class="iframe-app" src="/documents/preview/${id}"></iframe>`);
    parent.append(novoIframe);
}

function previewDocument(id, title = '', icon = '') {
    cleanPreviewDocument();
    documentTitle = title;
    $('#previewDocumentModalLabel').text(title || 'Emitir documento');
    $('#previewDocumentModalIcon').attr('class', icon || 'far fa-file-alt');
    $('#form-iframe-preview input[name=id]').val(id);
    $('#document_id').val(id);
    carregarPreview(id);
    loadDocumentFields(id);

    $('#previewDocumentModal').modal('show');
}

function loadDocumentFields(id) {
    $.ajax({
        url: `/documents/fields/${id}`,
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            documentHasAgenda = !!response.has_agenda;
            $('#document-fields > .row').html(response.html);
            $('.doc-step-fields').toggleClass('d-none', !$.trim(response.html));
            initOnlyDatePickers(sendDataByDocuments.bind(null, id));
        },
        error: function (jqXHR) {
            errorResponseJson(jqXHR, '#form-search-patients-by-documents');
        }
    });
}

function loadFirstSessionDate(patientId) {
    if (!elementExists('#field-date')) {
        return;
    }

    $.ajax({
        url: `/documents/first-session/${patientId}`,
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            $('#field-date').val(response.date || '');
            sendDataByDocuments(getDocumentIdByDoc());
        }
    });
}

function onPatientSelectedByDocuments(patientId) {
    togglePreviewPlaceholder(false);
    toggleEmitDocument(true);
    loadPatientAgendas(patientId);
    loadFirstSessionDate(patientId);
    sendDataByDocuments(getDocumentIdByDoc());
}

function saveDocumentDefault(fieldKey) {
    sendRequestByJson(
        `/documents/defaults/${getDocumentIdByDoc()}`,
        'POST',
        { field_key: fieldKey, value: $(`#field-${fieldKey}`).val() }
    );
}

function resetDocumentDefault(fieldKey) {
    sendRequestByJson(
        `/documents/defaults/${getDocumentIdByDoc()}`,
        'DELETE',
        { field_key: fieldKey },
        function (response) {
            $(`#field-${fieldKey}`).val(response.value);
            sendDataByDocuments(getDocumentIdByDoc());
            successResponseJson(response.title, response.message);
        }
    );
}

$(document).on('input change', '#document-fields .document-field', function () {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(function () {
        sendDataByDocuments(getDocumentIdByDoc());
    }, 500);
});

function emitDocument() {
    const documentId = $('#form-iframe-preview input[name=id]').val();

    if (!documentId || !$('#patient_name').val()) {
        return;
    }

    const fileName = getDocumentFileName();

    const selectorForm = '#form-search-patients-by-documents';

    // fetch em vez de $.ajax: com resposta em blob o jQuery não expõe o JSON de erro (ex.: limite do plano)
    loadingAjax();

    fetch(`/documents/pdf/${documentId}`, {
        method: 'POST',
        body: new URLSearchParams($(selectorForm).serialize()),
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/pdf, application/json'
        }
    }).then(async function (response) {
        closeLoadingAjax();

        if (!response.ok) {
            const json = await response.json().catch(function () { return {}; });
            errorResponseJson({ status: response.status, responseJSON: json }, selectorForm);
            return;
        }

        const url = URL.createObjectURL(await response.blob());
        const link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        link.click();
        URL.revokeObjectURL(url);
        $('#previewDocumentModal').modal('hide');
    }).catch(function () {
        closeLoadingAjax();
        errorResponseJson({ message: 'Não foi possível emitir o documento. Tente novamente.' }, selectorForm);
    });
}

function loadPatientAgendas(patientId) {
    if (!documentHasAgenda) {
        return;
    }

    $('.field-agenda-doc').removeClass('d-none');

    if (!patientId) {
        return;
    }

    resetAgendaSelection();
    getAgendaPatientByDocuments(patientId, 1);
}

function bindAgendaPaginationLinks() {
    $(document).off('click', '#agenda-selection-list .js-page-link').on('click', '#agenda-selection-list .js-page-link', function (e) {
        e.preventDefault();

        const patientId = $(this).data('patient-id');
        const page = Number($(this).data('page'));
        const callback = $(this).data('callback') || 'getAgendaPatientByDocuments';

        if (typeof window[callback] === 'function') {
            window[callback](patientId, page);
            return;
        }

        getAgendaPatientByDocuments(patientId, page);
    });
}

function getAgendaPatientByDocuments(patientId, page, callbackSuccess, callbackError) {
    if (!documentHasAgenda || !patientId) {
        return;
    }

    const selector = '#agenda-selection-list';
    const form = '#form-search-patients-by-documents';

    $.ajax({
        url: `/documents/agenda-by-patient/${patientId}`,
        type: 'GET',
        data: $(form).find(':input').not('[name="agenda_check[]"]').serialize() + `&page=${page}`,
        beforeSend: function () {
            $(selector).html('<div class="text-muted small">Carregando agendas...</div>');
        },
        success: function (response) {
            closeLoadingAjax();
            $(selector).html(response.html);
            bindAgendaPaginationLinks();
            restoreAgendaChecks();
            sendDataByDocuments(getDocumentIdByDoc());

            if (isFunction(callbackSuccess)) {
                callbackSuccess(response);
            }
        },
        error: function (jqXHR) {
            closeLoadingAjax();
            $(selector).html('<div class="text-danger small">Não foi possível carregar as agendas.</div>');

            if (isFunction(callbackError)) {
                callbackError(jqXHR);
            }
        }
    });
}

$(document).on('change', '#agenda-date-from, #agenda-date-to', function () {
    getAgendaPatientByDocuments($('#patient_id').val(), 1);
});

function sendDataByDocuments(documentId) {
    const form = '#form-search-patients-by-documents';

    // O preview só é atualizado depois de escolher o paciente.
    if (!documentId || !$('#patient_name').val()) {
        return;
    }

    sendRequest(
        '/documents/preview/' + documentId,
        'POST',
        form,
        'html',
        function(html) {
            document.getElementById("iframe-preview").srcdoc = html;
        },
        false
    );
}

function clearAgendaSelection() {
    $('#agenda-date-from, #agenda-date-to').val('');
    resetAgendaSelection();

    const patientId = $('#patient_id').val();

    if (patientId) {
        // Recarrega a lista sem o filtro de datas; o callback já restaura os checks e atualiza o preview.
        getAgendaPatientByDocuments(patientId, 1);
        return;
    }

    restoreAgendaChecks();
    sendDataByDocuments(getDocumentIdByDoc());
}
