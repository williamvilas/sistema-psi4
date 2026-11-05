/**
 * Painel de evolução do paciente (ficha do paciente).
 * - Frequência: indicadores calculados no servidor a partir da agenda.
 * - Escalas (PHQ-9, GAD-7, DASS-21, PCL-5): respostas e pontuação são cifradas no navegador antes do envio
 *   e decifradas aqui para montar o gráfico; o servidor só conhece a escala e a data.
 */
const formStoreScaleApplication = '#form-store-scale-application';
const SCALE_CONTENT_TYPE = 'application/octet-stream';

let scalesCatalog = null;
let patientScaleApplications = null;
let selectedChartScale = null;
let currentScaleSlug = null;
let closeScaleModalConfirmed = false;

$(document).ready(function () {
    if (!elementExists('#patient-evolution')) return;

    initPatientEvolution();
});

function initPatientEvolution() {
    paginatePatientEvolution();

    $('#patient-evolution-period').on('change', function () {
        paginatePatientEvolution();
        renderPatientScales();
        renderPatientThermometer();
    });

    $('#btn-unlock-scales').on('click', () => loadPatientScales());
    //$('#btn-apply-scale').on('click', openScaleApplicationModal);
    $(formStoreScaleApplication).on('change', 'input[name="scale"]', changeScaleApplication);
    $('#scaleApplicationModal').on('hide.bs.modal', confirmCloseScaleApplication);
    $('#patient-scales-tabs').on('click', '[data-chart-scale]', function () {
        selectedChartScale = $(this).data('chart-scale');
        renderPatientScales();
    });
    $(formStoreScaleApplication).on('change', 'input[name^="answers["]', answerScaleItem);
    $('#patient-scales-list').on('click', '[data-delete-scale]', function () {
        const application = $('#patient-scales-list').data('applications')[Number($(this).data('delete-scale'))];
        deleteScaleApplication(application.id, application.name + ' de ' + application.applied_on, application.within_retention);
    });
    $(formStoreScaleApplication).on('submit', function (e) {
        e.preventDefault();
        storeScaleApplication();
    });

    // Com a chave já desbloqueada, os resultados abrem sem pedir nada.
    getCachedMedicalRecordKey().then(function (key) {
        if (key) {
            loadPatientScales(true);
        }
    });
}

function getPatientEvolutionId() {
    return $('#patient-evolution').data('patient-id');
}

function getPatientEvolutionPeriod() {
    return Number($('#patient-evolution-period').val());
}

function getScalesCatalog() {
    if (!scalesCatalog) {
        scalesCatalog = JSON.parse($('#scales-catalog').text() || '[]');
    }

    return scalesCatalog;
}

function getScaleDefinition(slug) {
    return getScalesCatalog().find((scale) => scale.slug === slug) || null;
}

// --- Frequência (servidor) ---

function paginatePatientEvolution(callback = null) {
    const container = $('#patient-evolution-indicators');

    $.ajax({
        url: '/patients/' + encodeURIComponent(getPatientEvolutionId()) + '/evolution',
        type: 'GET',
        data: { period: getPatientEvolutionPeriod() },
        dataType: 'json',
        beforeSend: function () {
            container.addClass('is-loading');
        },
        success: function (response) {
            container.html(response.html);
            if (callback) callback();
        },
        error: function (jqXHR) {
            errorResponseJson(jqXHR, '');
        },
        complete: function () {
            container.removeClass('is-loading');
        }
    });
}

// --- Escalas: leitura ---

// silent: carga automática da página, sem o loading global (que derrubaria o toast do redirect).
async function loadPatientScales(silent = false) {
    const key = await unlockMedicalRecordKey();
    if (!key) return;

    const data = await requestPatientScales(silent);
    if (!data) return;

    try {
        if (!silent) loadingAjax();
        patientScaleApplications = await decryptScaleApplications(data.applications);
        $('#patient-scales-locked').addClass('d-none');
        $('#patient-scales-content').removeClass('d-none');
        renderPatientScales();
        loadPatientThermometer();
    } catch (error) {
        console.error(error);
        clickErrorMessage('Algo não deu certo', 'Não foi possível abrir os resultados das escalas. Por favor, tente novamente.');
    } finally {
        closeLoadingAjax();
    }
}

function requestPatientScales(silent = false) {
    return new Promise(function (resolve) {
        if (silent) {
            $.ajax({
                url: '/scale-applications/list',
                type: 'POST',
                dataType: 'json',
                data: { patient_id: getPatientEvolutionId() },
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: resolve,
                error: function (jqXHR) {
                    errorResponseJson(jqXHR, '');
                    resolve(null);
                }
            });
            return;
        }

        sendRequestByJson('/scale-applications/list', 'POST', { patient_id: getPatientEvolutionId() }, resolve, function (jqXHR) {
            errorResponseJson(jqXHR, '');
            resolve(null);
        });
    });
}

/**
 * Baixa e decifra as aplicações (também usado na exportação dos prontuários).
 * Cada item volta com os metadados do servidor e o conteúdo decifrado (respostas, pontuação e faixa).
 */
async function decryptScaleApplications(applications) {
    const result = [];

    for (const application of applications || []) {
        if (application.encryption === 'ecdh') {
            result.push(await decryptScaleAnsweredByPatient(application));
            continue;
        }

        const content = JSON.parse(await downloadAndDecryptMedicalRecord(application.link, application.content_path));
        result.push({ ...application, ...content });
    }

    return result;
}

// Respondida pelo paciente por link: o arquivo traz só as respostas; a pontuação é calculada aqui, com o catálogo.
async function decryptScaleAnsweredByPatient(application) {
    const content = JSON.parse(await downloadAndDecryptScaleFromPatient(application.link, application.scale, application.content_path));
    const definition = getScaleDefinition(application.scale);

    return { ...application, ...content, ...(definition ? calculateScaleResult(definition, content.answers) : {}) };
}

function filterScalesByPeriod(applications) {
    const months = getPatientEvolutionPeriod();
    if (!months) return applications;

    const since = moment().subtract(months, 'months').format('YYYY-MM-DD');

    return applications.filter((application) => application.applied_on_iso >= since);
}

function renderPatientScales() {
    if (!patientScaleApplications) return;

    const applications = filterScalesByPeriod(patientScaleApplications);
    const empty = !applications.length;

    $('#patient-scales-empty').toggleClass('d-none', !empty);
    $('#patient-scales-chart, #patient-scales-tabs').toggleClass('d-none', empty);

    renderScalesTabs(applications);
    renderScalesChart(applications.filter((application) => application.scale === selectedChartScale));
    renderScalesList(applications);
}

// Uma escala por vez no gráfico: as escalas têm máximos e faixas diferentes.
function renderScalesTabs(applications) {
    const slugs = [...new Set(applications.map((application) => application.scale))];

    if (!slugs.includes(selectedChartScale)) {
        selectedChartScale = applications.length ? applications[applications.length - 1].scale : null;
    }

    $('#patient-scales-tabs').html(slugs.map(function (slug) {
        const name = (getScaleDefinition(slug) || { name: slug }).name;
        const active = slug === selectedChartScale;

        return '<button type="button" class="dash-filter-chip' + (active ? ' active' : '') + '" data-chart-scale="' + slug + '" aria-pressed="' + active + '">' +
            $('<div>').text(name).html() + '</button>';
    }).join(''));
}

function renderScalesChart(applications) {
    if (!elementExists('#patient-scales-chart') || !applications.length) return;

    const scale = getScaleDefinition(applications[0].scale) || { name: applications[0].name, ranges: [] };
    const x = (application) => Date.parse(application.applied_on_iso + 'T12:00:00');
    const bandColors = ['rgba(28,138,86,.07)', 'rgba(178,122,0,.06)', 'rgba(178,122,0,.1)', 'rgba(214,69,69,.08)', 'rgba(214,69,69,.13)'];

    // Com subescalas (DASS-21), uma linha por subescala; as faixas variam por subescala e ficam no tooltip.
    const series = scale.subscales
        ? scale.subscales.map((subscale) => ({
            name: subscale.label,
            data: applications.map(function (application) {
                const result = (application.subscales || []).find((item) => item.key === subscale.key) || {};

                return { x: x(application), y: result.score, range: result.range, max: result.max };
            }),
        }))
        : [{
            name: scale.name,
            data: applications.map((application) => ({ x: x(application), y: application.score, range: application.range, max: application.max })),
        }];

    Highcharts.chart('patient-scales-chart', {
        chart: { type: 'line', height: 280, style: { fontFamily: 'inherit' } },
        title: { text: null },
        credits: { enabled: false },
        xAxis: { type: 'datetime', labels: { format: '{value:%d/%m/%y}' } },
        yAxis: {
            title: { text: 'Pontuação' },
            min: 0,
            max: scale.subscales ? scale.subscales[0].max : scale.max,
            allowDecimals: false,
            plotBands: scale.subscales ? [] : scale.ranges.map((range, i) => ({
                from: range.min - 0.5,
                to: range.max + 0.5,
                color: bandColors[range.level] || bandColors[i],
                label: { text: range.label, align: 'right', x: -6, style: { color: '#6b7a90', fontSize: '11px' } },
            })),
        },
        tooltip: {
            xDateFormat: '%d/%m/%Y',
            pointFormat: '<b>{series.name}</b>: {point.y} de {point.max} ({point.range})<br>',
            shared: true,
        },
        legend: { enabled: series.length > 1 },
        series: series.map((item) => ({ ...item, marker: { enabled: true, radius: 4 } })),
    });
}

// --- Termômetro emocional (dinâmica da sessão de vídeo): percepção do paciente, não é escala nem tem faixas ---
let patientThermometerRecords = null;

function loadPatientThermometer() {
    $.ajax({
        url: '/session-activities/thermometer',
        type: 'POST',
        dataType: 'json',
        data: { patient_id: getPatientEvolutionId() },
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: async function (response) {
            try {
                patientThermometerRecords = await decryptPatientThermometer(response.activities);
                renderPatientThermometer();
            } catch (error) {
                console.error(error);
            }
        },
        error: function (jqXHR) {
            errorResponseJson(jqXHR, '');
        }
    });
}

async function decryptPatientThermometer(activities) {
    const result = [];

    for (const activity of activities || []) {
        const record = await downloadAndDecryptSessionActivity(activity.link, activity.content_path);

        if (record.data.final !== null && record.data.final !== undefined) {
            result.push({ occurred_on: activity.occurred_on, value: record.data.final });
        }
    }

    return result;
}

function renderPatientThermometer() {
    if (!patientThermometerRecords) return;

    const months = getPatientEvolutionPeriod();
    const since = months ? moment().subtract(months, 'months').format('YYYY-MM-DD') : '';
    const records = patientThermometerRecords.filter((record) => record.occurred_on >= since);

    $('#patient-thermometer').toggleClass('d-none', !records.length);

    if (!records.length) return;

    Highcharts.chart('patient-thermometer-chart', {
        chart: { type: 'line', height: 220, style: { fontFamily: 'inherit' } },
        title: { text: null },
        credits: { enabled: false },
        legend: { enabled: false },
        xAxis: { type: 'datetime', labels: { format: '{value:%d/%m/%y}' } },
        yAxis: { title: { text: 'Percepção (0 a 10)' }, min: 0, max: 10, allowDecimals: false },
        tooltip: { xDateFormat: '%d/%m/%Y', pointFormat: 'Percepção informada: <b>{point.y}</b><br>' },
        series: [{
            name: 'Termômetro emocional',
            marker: { enabled: true, radius: 4 },
            data: records.map((record) => ({ x: Date.parse(record.occurred_on + 'T12:00:00'), y: record.value })),
        }],
    });
}

function renderScalesList(applications) {
    const list = $('#patient-scales-list');
    if (!list.length) return;

    const escape = (text) => $('<div>').text(text == null ? '' : String(text)).html();
    const previousBySlug = {};
    const rows = applications.map(function (application) {
        const previous = previousBySlug[application.scale];
        previousBySlug[application.scale] = application;

        // Subescalas (DASS-21) não têm pontuação total para comparar.
        const comparable = previous && application.score !== null && application.score !== undefined;

        return { application, diff: comparable ? application.score - previous.score : null };
    }).reverse();

    list.data('applications', rows.map((row) => row.application));
    list.html(rows.map(function ({ application, diff }, index) {
        const definition = getScaleDefinition(application.scale) || {};
        const criticalLabel = definition.critical_label || 'Atenção';
        // Em todas as escalas disponíveis, pontuação menor indica menos sintomas.
        const diffHtml = diff === null ? '' : (diff === 0
            ? '<span class="pat-scale-diff">= anterior</span>'
            : '<span class="pat-scale-diff ' + (diff < 0 ? 'is-better' : 'is-worse') + '">' + (diff > 0 ? '+' : '') + diff + ' desde a anterior</span>');

        return '<div class="pat-scale-row" data-scale-application="' + application.scale + '">' +
            '<div class="pat-scale-row-main">' +
                '<strong>' + escape(application.name) + '</strong>' +
                '<span class="pat-scale-date">' + escape(application.applied_on) + '</span>' +
                (application.encryption === 'ecdh' ? '<span class="pat-scale-date"><i class="fas fa-paper-plane me-1"></i>Respondida por link</span>' : '') +
            '</div>' +
            '<div class="pat-scale-row-result">' +
                (application.subscales
                    ? application.subscales.map((subscale) =>
                        '<span class="dash-chip ' + scaleLevelClass(subscale.level) + '" data-scale-subscale="' + escape(subscale.key) + '">' +
                            escape(subscale.label) + ': ' + escape(subscale.score) + ' · ' + escape(subscale.range) +
                        '</span>').join('')
                    : '<span class="pat-scale-score" data-scale-score>' + escape(application.score) + '<small>/' + escape(application.max) + '</small></span>' +
                      '<span class="dash-chip ' + scaleLevelClass(application.level) + '">' + escape(application.range) + '</span>') +
                (application.critical ? '<span class="dash-chip dash-chip-danger" title="' + escape(definition.critical_message || '') + '"><i class="fas fa-exclamation-triangle"></i> ' + escape(criticalLabel) + '</span>' : '') +
                diffHtml +
            '</div>' +
            '<button type="button" class="dash-round-btn dash-round-muted" title="Apagar" aria-label="Apagar" data-delete-scale="' + index + '">' +
                '<i class="far fa-trash-alt"></i>' +
            '</button>' +
        '</div>';
    }).join(''));
}

function scaleLevelClass(level) {
    return level >= 3 ? 'dash-chip-danger' : (level >= 1 ? 'dash-chip-warning' : 'dash-chip-success');
}

// --- Escalas: aplicação ---

function getSelectedScaleSlug() {
    return $(formStoreScaleApplication).find('input[name="scale"]:checked').val();
}

function getScaleModal() {
    return bootstrap.Modal.getOrCreateInstance(document.getElementById('scaleApplicationModal'));
}

function hasScaleAnswers() {
    return $(formStoreScaleApplication).find('input[name^="answers["]:checked').length > 0;
}

// A senha mestre é pedida antes de abrir: assim o Salvar não interrompe a aplicação no fim.
async function openScaleApplicationModal() {
    const key = await unlockMedicalRecordKey();
    if (!key) return;

    const form = $(formStoreScaleApplication);
    form[0].reset();
    form.find('[name="applied_on"]').val(moment().format('DD/MM/YYYY'));
    currentScaleSlug = getSelectedScaleSlug();
    renderScaleQuestions();

    closeScaleModalConfirmed = false;
    getScaleModal().show();
}

// Fechar (ESC, X, Cancelar) com respostas marcadas pede confirmação para não perder a aplicação.
function confirmCloseScaleApplication(e) {
    if (closeScaleModalConfirmed || !hasScaleAnswers()) return;

    e.preventDefault();
    confirmAction('Fechar sem salvar? As respostas marcadas serão descartadas.', function () {
        closeScaleModalConfirmed = true;
        getScaleModal().hide();
    }, 'warning', 'Fechar sem salvar', 'Continuar aplicando');
}

function changeScaleApplication() {
    const slug = getSelectedScaleSlug();
    if (slug === currentScaleSlug) return;

    if (!hasScaleAnswers()) {
        currentScaleSlug = slug;
        return renderScaleQuestions();
    }

    // Volta a seleção até a confirmação; só troca se a psicóloga aceitar descartar as respostas.
    $(formStoreScaleApplication).find('input[name="scale"][value="' + currentScaleSlug + '"]').prop('checked', true);
    confirmAction('Trocar de escala? As respostas marcadas serão descartadas.', function () {
        $(formStoreScaleApplication).find('input[name="scale"][value="' + slug + '"]').prop('checked', true);
        currentScaleSlug = slug;
        renderScaleQuestions();
    }, 'warning', 'Trocar escala', 'Continuar nesta');
}

function renderScaleQuestions() {
    const scale = getScaleDefinition(getSelectedScaleSlug());
    const container = $('#scale-questions');
    if (!scale) return container.empty();

    const escape = (text) => $('<div>').text(text).html();
    const hasCritical = scale.critical_item !== null && scale.critical_item !== undefined;

    $('#scale-instruction').text(scale.instruction);
    $('#scale-source').text(scale.source);
    $('#scale-reference-event').toggleClass('d-none', !scale.reference_event);
    $('#scale-progress').attr('aria-valuemax', scale.items.length);
    container[0].style.setProperty('--scale-options', scale.options.length);
    // Rótulos longos (DASS-21) ganham colunas mais largas; o texto do instrumento validado não é encurtado.
    container.toggleClass('pat-scale-options-long', scale.options.some((option) => option.label.length > 30));

    container.html(scale.items.map(function (item, index) {
        const options = scale.options.map(function (option) {
            const id = 'scale-item-' + index + '-' + option.value;

            return '<input type="radio" class="btn-check" name="answers[' + index + ']" id="' + id + '" value="' + option.value + '" autocomplete="off">' +
                '<label class="pat-scale-option" for="' + id + '">' +
                    '<span class="pat-scale-option-value">' + option.value + '</span>' +
                    '<span>' + escape(option.label) + '</span>' +
                '</label>';
        }).join('');

        // O acolhimento do item crítico aparece junto da pergunta que o disparou.
        const critical = hasCritical && index === scale.critical_item
            ? '<div class="alert alert-warning d-none pat-scale-critical" id="scale-critical" role="alert">' +
                '<i class="fas fa-hand-holding-heart"></i><p class="mb-0">' + escape(scale.critical_message || '') + '</p>' +
              '</div>'
            : '';

        return '<fieldset class="pat-scale-item" data-scale-item="' + index + '">' +
            '<legend><span class="pat-scale-item-number">' + (index + 1) + '</span><span>' + escape(item) + '</span></legend>' +
            '<div class="pat-scale-options" role="radiogroup">' + options + '</div>' +
            critical +
        '</fieldset>';
    }).join(''));

    $('#scaleApplicationModal .modal-body').scrollTop(0);
    updateScaleScore();
}

function answerScaleItem() {
    const item = $(this).closest('[data-scale-item]');
    const firstAnswer = !item.hasClass('is-answered');

    item.addClass('is-answered').removeClass('is-missing');
    updateScaleScore();

    // Só avança na primeira resposta do item; corrigir uma resposta não mexe na rolagem.
    if (firstAnswer) {
        scrollToNextScaleItem(Number(item.data('scale-item')));
    }
}

function scrollToNextScaleItem(index) {
    const body = document.querySelector('#scaleApplicationModal .modal-body');
    const next = $('#scale-questions [data-scale-item]').filter(function () {
        return Number($(this).data('scale-item')) > index && !$(this).hasClass('is-answered');
    }).first()[0];
    if (!body || !next) return;

    // Rola apenas quando o próximo item não está inteiro na área visível (descontando o topo fixo).
    const view = body.getBoundingClientRect();
    const rect = next.getBoundingClientRect();
    const stickyHeight = $('.pat-scale-sticky').outerHeight() || 0;
    if (rect.top >= view.top + stickyHeight && rect.bottom <= view.bottom) return;

    body.scrollTo({ top: body.scrollTop + rect.top - view.top - stickyHeight - 12, behavior: 'smooth' });
}

function getScaleAnswers(scale) {
    return scale.items.map(function (item, index) {
        const checked = $(formStoreScaleApplication).find('input[name="answers[' + index + ']"]:checked');

        return checked.length ? Number(checked.val()) : null;
    });
}

function findScaleRange(ranges, score) {
    return ranges.find((range) => score >= range.min && score <= range.max) || ranges[ranges.length - 1];
}

function sumScaleAnswers(answers, indexes) {
    return indexes.reduce((sum, index) => sum + (answers[index] || 0), 0);
}

/**
 * Pontuação conforme config/scales.php: soma simples classificada nas faixas da escala ou,
 * com subescalas (DASS-21), soma dos itens de cada uma × multiplicador, sem pontuação total.
 */
function calculateScaleResult(scale, answers) {
    const critical = scale.critical_item !== null && scale.critical_item !== undefined && (answers[scale.critical_item] || 0) > 0;

    if (scale.subscales) {
        const subscales = scale.subscales.map(function (subscale) {
            const score = sumScaleAnswers(answers, subscale.items) * (subscale.multiplier || 1);
            const range = findScaleRange(subscale.ranges, score);

            return { key: subscale.key, label: subscale.label, score, max: subscale.max, range: range.label, level: range.level };
        });

        return { score: null, max: null, range: null, level: Math.max(...subscales.map((item) => item.level)), critical, subscales };
    }

    const score = sumScaleAnswers(answers, answers.map((value, index) => index));
    const range = findScaleRange(scale.ranges, score);

    return { score, max: scale.max, range: range.label, level: range.level, critical };
}

// A pontuação só aparece com a escala completa, para não induzir a leitura durante a aplicação.
function updateScaleScore() {
    const scale = getScaleDefinition(getSelectedScaleSlug());
    if (!scale) return;

    const escape = (text) => $('<div>').text(text).html();
    const answers = getScaleAnswers(scale);
    const answered = answers.filter((value) => value !== null).length;
    const complete = answered === answers.length;
    const result = calculateScaleResult(scale, answers);

    $('#scale-progress').attr('aria-valuenow', answered);
    $('#scale-progress-bar').css('width', (answers.length ? answered / answers.length * 100 : 0) + '%');
    $('#scale-critical').toggleClass('d-none', !result.critical);

    const critical = result.critical
        ? '<span class="dash-chip dash-chip-danger"><i class="fas fa-exclamation-triangle"></i> ' + escape(scale.critical_label || 'Atenção') + '</span>'
        : '';

    if (!complete) {
        return $('#scale-summary').html('<span>Respondidos <strong>' + answered + '</strong> de ' + answers.length + '</span>' + critical);
    }

    const score = result.subscales
        ? result.subscales.map((subscale) =>
            '<span class="dash-chip ' + scaleLevelClass(subscale.level) + '">' + escape(subscale.label) + ': ' + subscale.score + ' · ' + escape(subscale.range) + '</span>').join('')
        : '<span>Pontuação</span><strong>' + result.score + ' de ' + result.max + '</strong>' +
          '<span class="dash-chip ' + scaleLevelClass(result.level) + '">' + escape(result.range) + '</span>';

    $('#scale-summary').html(score + critical);
}

async function storeScaleApplication() {
    const form = $(formStoreScaleApplication);
    const scale = getScaleDefinition(getSelectedScaleSlug());
    if (!scale) return;

    const answers = getScaleAnswers(scale);
    const missing = answers.map((value, index) => value === null ? index : null).filter((index) => index !== null);
    if (missing.length) {
        missing.forEach((index) => form.find('[data-scale-item="' + index + '"]').addClass('is-missing'));
        form.find('[data-scale-item="' + missing[0] + '"]')[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        const label = missing.length === 1 ? 'Falta 1 item' : 'Faltam ' + missing.length + ' itens';
        return clickWarningMessage('Quase lá', label + ' para salvar a escala. Os itens pendentes estão destacados.');
    }

    // A chave já foi pedida ao abrir; se expirou no meio da aplicação, é pedida de novo antes do loading.
    const key = await unlockMedicalRecordKey();
    if (!key) return;

    try {
        loadingAjax();

        const filename = 'scale-applications/' + generateFilename();
        const link = await requestUploadUrl(filename, SCALE_CONTENT_TYPE);
        if (!link) return;

        // O evento de referência (PCL-5) é texto clínico: vai só dentro do conteúdo cifrado.
        const referenceEvent = scale.reference_event ? String(form.find('[name="reference_event"]').val() || '').trim() : '';
        const content = JSON.stringify({
            version: scale.version,
            answers,
            ...calculateScaleResult(scale, answers),
            ...(referenceEvent ? { reference_event: referenceEvent } : {}),
        });
        const encrypted = await encryptMedicalContent(content, key, filename);
        await uploadToS3MedicalRecords(link, encrypted, SCALE_CONTENT_TYPE);

        sendRequestByJson('/scale-applications', 'POST', {
            patient_id: getPatientEvolutionId(),
            scale: scale.slug,
            applied_on: form.find('[name="applied_on"]').val(),
            content_path: filename,
        }, function (response) {
            closeLoadingAjax();
            closeScaleModalConfirmed = true;
            getScaleModal().hide();

            // A chave acabou de ser usada: já mostra o resultado no gráfico.
            loadPatientScales().then(() => successResponseJson(response.title, 'Escala salva com segurança.'));
        }, function (jqXHR) {
            closeLoadingAjax();
            errorResponseJson(jqXHR, formStoreScaleApplication);
        });
    } catch (error) {
        closeLoadingAjax();
        console.error(error);
        clickErrorMessage('Algo não deu certo', 'Não foi possível salvar a escala. Por favor, tente novamente.');
    }
}

function deleteScaleApplication(id, name, withinRetention = false) {
    const safeName = $('<div>').text(name).html();
    const data = { id: id, retention_confirmed: withinRetention ? 1 : 0 };
    const onDeleted = function (response) {
        patientScaleApplications = patientScaleApplications.filter((application) => application.id !== id);
        renderPatientScales();
        successResponseJson(response.title, response.message);
    };

    // Apagar exige a senha mestre (ou a chave já desbloqueada neste navegador).
    const remove = async function () {
        if (!await unlockMedicalRecordKey()) {
            return;
        }

        sendRequestByJson('/scale-applications', 'DELETE', data, onDeleted);
    };

    if (withinRetention) {
        return confirmMedicalRecordRetention(safeName, remove, 'A escala', 'Prazo de guarda da escala');
    }

    confirmAction('Tem certeza que deseja <strong>apagar</strong> a escala <strong>' + safeName + '</strong>?', remove, 'danger', 'Apagar', 'Cancelar');
}
