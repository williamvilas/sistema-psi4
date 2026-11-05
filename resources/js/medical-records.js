$(document).ready(function() {
    const formMedicalRecordsStore = '#form-store-medical-records';
    const formMedicalRecordsUpdate = '#form-update-medical-records';

    $(formMedicalRecordsStore).submit(async function (e) {
        e.preventDefault();
        await storeAndUploadMedicalRecords(formMedicalRecordsStore);
    });

    $(formMedicalRecordsUpdate).submit(async function (e) {
        e.preventDefault();

        confirmAction('Deseja <strong>salvar as alterações</strong> do prontuário?', async function() {
            await updateAndUploadMedicalRecords(formMedicalRecordsUpdate);
        }, 'question');
    });
});

// O conteúdo vem do S3 como HTML livre (colagem, IA); sempre sanitizar antes de renderizar ou salvar.
function sanitizeMedicalHtml(html) {
    return DOMPurify.sanitize(html || '');
}

// O conteúdo vem cifrado do storage; a decifragem acontece só aqui no navegador (ver crypto.js).
function loadMedicalRecordContent(link, notesPath, callbackSuccess, callbackError = null) {
    loadingAjax();

    downloadAndDecryptMedicalRecord(link, notesPath).then(function(response) {
        callbackSuccess(response);
        closeLoadingAjax();
    }).catch(function(error) {
        console.log('Error', error);
        closeLoadingAjax();

        if (isFunction(callbackError)) {
            callbackError(error);
        }
    });
}

function loadMedicalRecordShow(link, notesPath) {
    const showError = () => {
        $('#docLoading').addClass('d-none');
        $('#docError').removeClass('d-none');
    };

    if (!link) {
        $('#docLoading').addClass('d-none');
        $('#post').removeClass('d-none');
        $('#postContent').html('<p class="doc-page-empty">Sem conteúdo.</p>');
        return;
    }

    $('#docError').addClass('d-none');
    $('#docLoading').removeClass('d-none');

    downloadAndDecryptMedicalRecord(link, notesPath).then(function(response) {
        const html = sanitizeMedicalHtml(response).trim();

        $('#docLoading').addClass('d-none');
        $('#post').removeClass('d-none');
        $('#postContent').html(html || '<p class="doc-page-empty">Sem conteúdo.</p>');
        $('#postContent a[href]').attr({ target: '_blank', rel: 'noopener noreferrer' });
    }).catch(function(error) {
        console.log('Error', error);

        // Cancelou a senha mestre: volta para a lista em vez de mostrar erro.
        if (error.cancelled) {
            return redirectTo('/medical-records');
        }

        showError();
    });
}

// Visualização somente leitura do prontuário (mesmo visual de documento do editor).
function initMedicalRecordShow(link, notesPath = null) {
    if (!elementExists('#post')) return;

    document.body.classList.add('doc-mode');

    loadMedicalRecordShow(link, notesPath);

    $('#docRetry').on('click', () => window.location.reload());
    $('#docPrint').on('click', () => window.print());

    document.addEventListener('keydown', function(e) {
        if (!(e.ctrlKey || e.metaKey) || e.shiftKey || e.altKey) return;

        const key = e.key.toLowerCase();

        if (key === 'p') {
            e.preventDefault();
            window.print();
            return;
        }

        if (key === 'e') {
            e.preventDefault();
            window.location.href = $('#docEdit').attr('href');
        }
    });
}

function httpError(message, status) {
    const error = new Error(message);
    error.status = status;

    return error;
}

// O rascunho só é guardado em falha de conexão (sem status) ou erro 500; sessão expirada é tratada em handleSessionExpired.
function shouldKeepMedicalDraft(status) {
    return !status || status >= 500;
}

async function updateAndUploadMedicalRecords(form) {
    await uploadAndSendMedicalRecords(form, (formSelector, notesPath) => updateMedicalRecords(formSelector, notesPath));
}

async function storeAndUploadMedicalRecords(form) {
    await uploadAndSendMedicalRecords(form, storeMedicalRecords);
}

async function uploadAndSendMedicalRecords(form, callbackSend) {
    // A chave é obtida antes do loading: pode ser preciso pedir a senha mestre.
    const key = await unlockMedicalRecordKey();
    if (!key) {
        return;
    }

    try {
        loadingAjax();
        const filename = `medical-records/${generateFilename()}`;
        const contentType = 'application/octet-stream';

        const link = await requestUploadUrl(filename, contentType);
        if (!link) {
            return;
        }

        const encrypted = await encryptMedicalContent(MedicalEditor.getHTML(), key, filename);

        await uploadToS3MedicalRecords(link, encrypted, contentType);
        await callbackSend(form, filename);
    } catch (error) {
        closeLoadingAjax();
        console.error(error);

        if (shouldKeepMedicalDraft(error.status)) {
            await MedicalEditor.saveDraftNow();
        }

        clickErrorMessage('Algo não deu certo', 'Não foi possível salvar o prontuário. Por favor, tente novamente mais tarde');
    }
}

// URL pré-assinada para enviar o conteúdo já cifrado direto ao S3; null quando a sessão expirou.
async function requestUploadUrl(filename, contentType) {
    const response = await fetch('/files/upload-url', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        body: JSON.stringify({
            path: filename,
            content_type: contentType,
        })
    });

    if (isSessionExpiredResponse(response)) {
        handleSessionExpired();
        return null;
    }

    if (!response.ok) {
        throw httpError(`Erro ao gerar URL de upload: ${response.status}`, response.status);
    }

    return (await response.json()).link;
}

async function updateMedicalRecords(form, notesPath) {
    const data = $(form).serialize() + '&notes_path=' + notesPath;

    $.ajax({
        url: '/medical-records/' + getId(),
        type: 'PUT',
        data: data,
        dataType: 'json',
        success: function(response) {
            MedicalEditor.markSaved();
            return redirectTo(response.link);
        },
        error: function(xhr, status, error) {
            closeLoadingAjax();

            if (shouldKeepMedicalDraft(xhr.status)) {
                MedicalEditor.saveDraftNow();
            }

            errorResponseJson(xhr, form)
        },
        complete: function(jqXHR) {}
    });
}

async function storeMedicalRecords(form, notesPath) {
    const data = $(form).serialize() + '&notes_path=' + notesPath;

    $.ajax({
        url: '/medical-records',
        type: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            MedicalEditor.markSaved();
            return redirectTo(response.link);
        },
        error: function(xhr, status, error) {
            closeLoadingAjax();

            if (shouldKeepMedicalDraft(xhr.status)) {
                MedicalEditor.saveDraftNow();
            }

            errorResponseJson(xhr, form)
        },
        complete: function(jqXHR) {}
    });
}

async function uploadToS3MedicalRecords(url, content, contentType) {

    const response = await fetch(url, {
        method: 'PUT',
        headers: {
            'Content-Type': contentType,
        },
        body: new Blob([content], { type: contentType }),
    });

    if (!response.ok) {
        throw httpError(`Erro ao enviar arquivo: ${response.status}`, response.status);
    }

    return true;
}

function addResultIAInEditor() {
    MedicalEditor.setHTML($('#aiAssistantResult').html());
}

async function assistantIAMedicalRecords() {
    try {
        $('#copyAiAssistantResult').removeClass('d-none');
        $('#copyAiAssistantResult').html('<i class="fas fa-spinner fa-spin me-1"></i> Carregando...');
        $('#aiGenerateBtn, #copyAiAssistantResult, #ai_prompt, #aiInsertBtn').attr('disabled', 'disabled');

        const prompt = $('#ai_prompt').val();

        const result = await requestMedicalRecordsIA({ prompt: prompt });
        if (result.reached) {
            $('#aiAssistantResult').html(`<p class="text-danger error-chat">${$('<div>').text(result.message).html()}</p>`);
            $('#copyAiAssistantResult').addClass('d-none');
            return;
        }

        const response = result.response;

        if (response.status === 401) {
            const data = await response.json();
            $('#aiAssistantResult').html(`<p class="text-danger error-chat">${$('<div>').text(data.error).html()}</p>`);
            $('#copyAiAssistantResult').addClass('d-none');
            return;
        }

        if (!response.ok) {
            throw new Error(`Erro na API: ${response.status}`);
        }

        if (!response.body) {
            throw new Error("Resposta sem corpo (body vazio)");
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder("utf-8");

        let textoFinal = "";

        while (true) {
            let result;

            try {
                result = await reader.read();
            } catch (streamError) {
                throw new Error("Erro ao ler o stream da resposta");
            }

            const { done, value } = result;

            if (done) {
                $('#copyAiAssistantResult').html('<i class="fas fa-copy me-1"></i> Copiar');
                break;
            }

            const chunk = decoder.decode(value, { stream: true });
            textoFinal += chunk;

            $("#aiAssistantResult").html(textoFinal);
        }

        $('#aiInsertBtn').removeAttr('disabled');

    } catch (error) {
        console.error(error);
        sendLog('Não foi possível gerar a prescrição com IA.', 'medical-records.js->assistantIAMedicalRecords');
        $("#aiAssistantResult").html(
            `<p class="text-danger error-chat">Não foi possível gerar a prescrição.<br/>Por favor, tente mais tarde.</p>`
        );

        $('#copyAiAssistantResult').addClass('d-none');
    } finally {
        $('#aiGenerateBtn, #copyAiAssistantResult, #ai_prompt').removeAttr('disabled');
    }
}

let paginateMedicalRecordsSeq = 0;
let searchMedicalRecordsTimer = null;

function paginateMedicalRecords(callback = null, loading = true) {
    const form = '#form-search';
    const seq = ++paginateMedicalRecordsSeq;
    clearTimeout(searchMedicalRecordsTimer);
    totalFilters('#filterModal');
    syncMedicalRecordsChips();
    $('#filterModal').modal('hide');

    sendRequest(
        '/medical-records/paginate',
        'GET',
        form,
        'json',
        function(response) {
            // ignora respostas de buscas antigas que chegaram depois da mais recente
            if (seq !== paginateMedicalRecordsSeq) {
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

function clearFiltersMedicalRecords() {
    clearForm();
    $('#form-search #patient_id').val(null).trigger('change');
    paginateMedicalRecords();
}

function initMedicalRecordsSearch() {
    const selector = '#medical-records-search';

    if (!elementExists(selector)) {
        return;
    }

    // busca sem o loading em tela cheia, para não tirar o foco do campo enquanto digita
    $(selector).on('input', function () {
        clearTimeout(searchMedicalRecordsTimer);
        searchMedicalRecordsTimer = setTimeout(function () {
            paginateMedicalRecords(null, false);
        }, 400);
    });
}

function getMedicalRecordsPeriodRange(period) {
    const format = 'DD/MM/YYYY';
    const today = moment();

    switch (period) {
        case 'today':
            return today.format(format) + ' - ' + today.format(format);
        case 'last-7-days':
            return moment().subtract(6, 'days').format(format) + ' - ' + today.format(format);
        case 'month':
            return moment().startOf('month').format(format) + ' - ' + moment().endOf('month').format(format);
        default:
            return '';
    }
}

function filterMedicalRecordsPeriod(period) {
    $('#search_date').val(getMedicalRecordsPeriodRange(period));
    paginateMedicalRecords();
}

function syncMedicalRecordsChips() {
    if (!elementExists('.dash-filter-chip')) {
        return;
    }

    const q = $.trim($('#medical-records-search').val() || '');
    const patient = $('#form-search #patient_id').val() || '';
    const date = $('#search_date').val() || '';
    let active = null;

    if (!q && !patient && !date) {
        active = 'all';
    } else {
        active = ['today', 'last-7-days', 'month'].find(function (period) {
            return date === getMedicalRecordsPeriodRange(period);
        }) || null;
    }

    $('.dash-filter-chip').removeClass('active');
    if (active) {
        $('.dash-filter-chip[data-chip="' + active + '"]').addClass('active');
    }
}

function deleteMedicalRecords(id, name, redirect = false, withinRetention = false) {
    const safeName = $('<div>').text(name).html();
    const onDeleted = function(response) {
        if (redirect) {
            return redirectTo(response.link);
        }

        paginateMedicalRecords(function() {
            successResponseJson(response.title, response.message);
        });
    };

    // Apagar exige a senha mestre (ou a chave já desbloqueada neste navegador).
    const remove = async function() {
        if (!await unlockMedicalRecordKey()) {
            return;
        }

        sendRequestByJson('/medical-records', 'DELETE', { ids: [id], retention_confirmed: withinRetention ? 1 : 0 }, onDeleted);
    };

    if (withinRetention) {
        return confirmMedicalRecordRetention(safeName, remove);
    }

    confirmAction('Tem certeza que deseja <strong>apagar</strong> o prontuário de <strong>' + safeName + '</strong>?', remove, 'danger', 'Apagar', 'Cancelar');
}

// Res. CFP 001/2009: registro com menos de 5 anos (prontuário ou escala) só é apagado após ciência explícita do prazo de guarda.
function confirmMedicalRecordRetention(safeName, callback, subject = 'O prontuário de', title = 'Prazo de guarda do prontuário') {
    appDialog({
        tone: 'danger',
        title: title,
        html: subject + ' <strong>' + safeName + '</strong> tem menos de 5 anos. ' +
            'A Resolução CFP 001/2009 exige a guarda do registro documental por, no mínimo, 5 anos. ' +
            'Depois de apagado, o conteúdo não poderá ser recuperado.',
        content: '<label class="app-dialog-check" for="retention-confirmed">' +
            '<input class="form-check-input" type="checkbox" id="retention-confirmed">' +
            '<span>Estou ciente e assumo a responsabilidade pela exclusão.</span>' +
            '</label>',
        confirmText: 'Apagar',
        cancelText: 'Cancelar',
        preConfirm: function() {
            if (!$('#retention-confirmed').is(':checked')) {
                Swal.showValidationMessage('Marque a confirmação para apagar.');
                return false;
            }

            return true;
        },
    }).then(function(result) {
        if (result.isConfirmed) {
            callback();
        }
    });
}

/**
 * Exporta todos os prontuários do paciente num documento único (HTML pronto para imprimir/salvar em PDF).
 * O conteúdo é baixado cifrado e decifrado aqui no navegador; o servidor nunca vê o texto.
 */
async function exportMedicalRecords(patientId, patientName) {
    const key = await unlockMedicalRecordKey();
    if (!key) {
        return;
    }

    const data = await new Promise(function(resolve) {
        sendRequestByJson('/medical-records/export', 'POST', { patient_id: patientId }, resolve, function(jqXHR) {
            errorResponseJson(jqXHR, '');
            resolve(null);
        });
    });
    if (!data) {
        return;
    }

    if (!(data.records || []).length && !(data.scales || []).length) {
        return clickWarningMessage('Nenhum prontuário ainda', 'Este paciente ainda não tem prontuários.');
    }

    try {
        loadingAjax();

        const sections = [];
        for (const record of data.records) {
            const html = await downloadAndDecryptMedicalRecord(record.link, record.notes_path);
            sections.push({ ...record, html: sanitizeMedicalHtml(html) });
        }

        const scales = await decryptScaleApplications(data.scales);

        downloadMedicalRecordsDocument(data.patient, sections, scales);
        closeLoadingAjax();
        successResponseJson('Prontuários exportados', 'O arquivo foi baixado. Abra-o no navegador para imprimir ou salvar em PDF. Ele contém dados sensíveis: guarde-o em local seguro.');
    } catch (error) {
        console.error(error);
        closeLoadingAjax();
        clickErrorMessage('Algo não deu certo', 'Não foi possível exportar os prontuários. Por favor, tente novamente.');
    }
}

function downloadMedicalRecordsDocument(patientName, records, scales = []) {
    const escape = (text) => $('<div>').text(text || '').html();
    const page = $('#patient-show-page');
    const professional = escape(page.data('professional'));
    const crp = page.data('crp') ? ' · CRP ' + escape(page.data('crp')) : '';
    const issuedAt = new Date().toLocaleString('pt-BR');

    const body = records.map(function(record) {
        return '<section class="record">' +
            '<h2>' + escape(record.title) + '</h2>' +
            '<p class="meta">Data da sessão: ' + escape(record.date) + '</p>' +
            '<div class="content">' + (record.html.trim() || '<p><em>Sem conteúdo.</em></p>') + '</div>' +
            '</section>';
    }).join('') + exportScalesSection(scales, escape);

    const documentHtml = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">' +
        '<title>Prontuários - ' + escape(patientName) + '</title>' +
        '<style>body{font-family:Arial,sans-serif;color:#202124;max-width:800px;margin:2rem auto;padding:0 1rem;line-height:1.5}' +
        'header{border-bottom:2px solid #1f7a80;margin-bottom:1.5rem;padding-bottom:.75rem}h1{font-size:1.4rem;margin:0}' +
        '.record{page-break-inside:avoid;border-bottom:1px solid #dadce0;padding:1rem 0}.record h2{font-size:1.1rem;margin:0}' +
        '.meta{color:#5f6368;font-size:.9rem;margin:.25rem 0 .75rem}footer{color:#5f6368;font-size:.8rem;margin-top:2rem}' +
        'table{border-collapse:collapse}td,th{border:1px solid #dadce0;padding:4px}@media print{body{margin:0}}</style></head><body>' +
        '<header><h1>Registro documental - ' + escape(patientName) + '</h1>' +
        '<p class="meta">Profissional: ' + professional + crp + ' · Emitido em ' + escape(issuedAt) + ' · ' + records.length + ' registro(s)' + (scales.length ? ' · ' + scales.length + ' escala(s)' : '') + '</p></header>' +
        body +
        '<footer>Documento sigiloso (Resolução CFP 001/2009 e LGPD). Contém dados pessoais sensíveis de saúde.</footer>' +
        '</body></html>';

    const url = URL.createObjectURL(new Blob([documentHtml], { type: 'text/html;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = 'prontuarios-' + String(patientName).normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^A-Za-z0-9]+/g, '-').toLowerCase() + '.html';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

// Escalas aplicadas: pontuação, faixa e respostas item a item (textos do catálogo da ficha do paciente).
function exportScalesSection(scales, escape) {
    if (!scales.length) return '';

    return '<section class="record"><h2>Escalas aplicadas</h2></section>' + scales.map(function(application) {
        const definition = getScaleDefinition(application.scale);
        const answers = definition ? '<table><tbody>' + application.answers.map(function(value, index) {
            const option = definition.options.find((item) => item.value === value);

            return '<tr><td>' + (index + 1) + '. ' + escape(definition.items[index]) + '</td><td>' + escape(option ? option.label : value) + ' (' + escape(value) + ')</td></tr>';
        }).join('') + '</tbody></table>' : '';

        const result = application.subscales
            ? application.subscales.map((subscale) => escape(subscale.label) + ': ' + escape(subscale.score) + ' de ' + escape(subscale.max) + ' (' + escape(subscale.range) + ')').join(' · ')
            : 'Pontuação: ' + escape(application.score) + ' de ' + escape(application.max) + ' · Faixa: ' + escape(application.range);

        return '<section class="record">' +
            '<h2>' + escape(application.name) + '</h2>' +
            '<p class="meta">Aplicada em: ' + escape(application.applied_on) + ' · ' + result + '</p>' +
            (application.reference_event ? '<p class="meta">Evento de referência: ' + escape(application.reference_event) + '</p>' : '') +
            answers +
            '</section>';
    }).join('');
}

// Painéis laterais das telas de documento (IA, Acessos): um aberto por vez, largura compartilhada e redimensionável.
const DOC_PANEL_W_MIN = 320, DOC_PANEL_W_MAX = 560;

function readDocPanelWidth() {
    try { return parseInt(localStorage.getItem('docPanel.width'), 10) || null; } catch (e) { return null; }
}

function writeDocPanelWidth(width) {
    try { localStorage.setItem('docPanel.width', width); } catch (e) { /* ignora */ }
}

function setDocPanelWidth(width) {
    document.documentElement.style.setProperty('--doc-panel-w', Math.min(DOC_PANEL_W_MAX, Math.max(DOC_PANEL_W_MIN, width)) + 'px');
}

// Abre/fecha o painel (open indefinido alterna). Dispara 'doc-panel:opened' / 'doc-panel:closed' no painel.
function toggleDocSidePanel(panelId, open) {
    const panel = $('#' + panelId);
    if (!panel.length) return false;

    const show = open === undefined ? panel.hasClass('d-none') : open;

    if (show) {
        $('.doc-side-panel').not(panel).not('.d-none').each(function () {
            $(this).addClass('d-none').trigger('doc-panel:closed');
            $('[aria-controls="' + this.id + '"]').attr('aria-expanded', false);
        });
    }

    const changed = panel.hasClass('d-none') === show;
    panel.toggleClass('d-none', !show);
    $('[aria-controls="' + panelId + '"]').attr('aria-expanded', show);
    $('body').toggleClass('doc-panel-open', $('.doc-side-panel:not(.d-none)').length > 0);

    if (changed) panel.trigger(show ? 'doc-panel:opened' : 'doc-panel:closed');

    return show;
}

function initDocSidePanels() {
    if (!elementExists('.doc-side-panel') || $('body').data('doc-panels-ready')) return;
    $('body').data('doc-panels-ready', true);

    const saved = readDocPanelWidth();
    if (saved) setDocPanelWidth(saved);

    $(document).on('mousedown', '.doc-side-panel-resizer', function (e) {
        e.preventDefault();
        $('body').addClass('doc-panel-resizing');

        const onMove = (ev) => setDocPanelWidth(window.innerWidth - ev.clientX);
        const onUp = () => {
            $('body').removeClass('doc-panel-resizing');
            writeDocPanelWidth(parseInt(getComputedStyle(document.documentElement).getPropertyValue('--doc-panel-w'), 10));
            $(document).off('mousemove', onMove).off('mouseup', onUp);
        };
        $(document).on('mousemove', onMove).on('mouseup', onUp);
    });
}
