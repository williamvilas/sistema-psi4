const photoCropState = {
    canvas: null,
    context: null,
    image: null,
    zoom: 1,
    offsetX: 0,
    offsetY: 0,
    dragging: false,
    startX: 0,
    startY: 0,
    startOffsetX: 0,
    startOffsetY: 0,
    pressX: 0,
    pressY: 0,
    hasSavedPhoto: false,
    tainted: false,
};

function setupPhotoCropper(savedPhotoUrl = null) {
    const canvas = document.getElementById('photoCanvas');
    const wrap = document.getElementById('photoCanvasWrap');
    const input = document.getElementById('fotoPsicologo');
    const zoomInput = document.getElementById('photoZoom');

    if (!canvas || !wrap || !input || !zoomInput) return;

    photoCropState.canvas = canvas;
    photoCropState.context = canvas.getContext('2d');
    drawPhotoPlaceholder();

    if (savedPhotoUrl) {
        photoCropState.hasSavedPhoto = true;
        const savedPhoto = new Image();
        savedPhoto.addEventListener('load', () => {
            if (photoCropState.image) return;
            photoCropState.tainted = true;
            const scale = Math.max(canvas.width / savedPhoto.width, canvas.height / savedPhoto.height);
            photoCropState.context.drawImage(
                savedPhoto,
                (canvas.width - savedPhoto.width * scale) / 2,
                (canvas.height - savedPhoto.height * scale) / 2,
                savedPhoto.width * scale,
                savedPhoto.height * scale
            );
        });
        savedPhoto.src = savedPhotoUrl;
    }

    input.addEventListener('change', () => {
        const file = input.files && input.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.addEventListener('load', () => {
            const image = new Image();
            image.addEventListener('load', () => {
                // Foto salva vem de outra origem (S3) e "suja" o canvas; um canvas novo permite exportar o recorte.
                if (photoCropState.tainted) replacePhotoCanvas();
                photoCropState.image = image;
                photoCropState.zoom = 1;
                photoCropState.offsetX = 0;
                photoCropState.offsetY = 0;
                zoomInput.value = '1';
                zoomInput.disabled = false;
                document.getElementById('photoZoomWrap')?.classList.remove('d-none');
                setRemovePhoto(false);
                drawPhoto();
            });
            image.src = reader.result;
        });
        reader.readAsDataURL(file);
    });

    zoomInput.addEventListener('input', () => {
        photoCropState.zoom = Number(zoomInput.value);
        drawPhoto();
    });

    wrap.addEventListener('pointerdown', (event) => {
        photoCropState.pressX = event.clientX;
        photoCropState.pressY = event.clientY;
        if (!photoCropState.image) return;
        photoCropState.dragging = true;
        photoCropState.startX = event.clientX;
        photoCropState.startY = event.clientY;
        photoCropState.startOffsetX = photoCropState.offsetX;
        photoCropState.startOffsetY = photoCropState.offsetY;
        wrap.setPointerCapture(event.pointerId);
    });

    wrap.addEventListener('pointermove', (event) => {
        if (!photoCropState.dragging) return;
        const ratio = photoCropState.canvas.width / wrap.clientWidth;
        photoCropState.offsetX = photoCropState.startOffsetX + (event.clientX - photoCropState.startX) * ratio;
        photoCropState.offsetY = photoCropState.startOffsetY + (event.clientY - photoCropState.startY) * ratio;
        drawPhoto();
    });

    ['pointerup', 'pointercancel'].forEach((eventName) => {
        wrap.addEventListener(eventName, (event) => {
            photoCropState.dragging = false;

            const moved = Math.hypot(event.clientX - photoCropState.pressX, event.clientY - photoCropState.pressY);
            if (eventName === 'pointerup' && moved < 5) input.click();
        });
    });

    document.getElementById('btn-remove-photo')?.addEventListener('click', confirmRemovePhoto);
    updateRemoveButton();
}

function prepareCroppedPhoto() {
    if (!photoCropState.image || !photoCropState.canvas) return;

    const croppedPhoto = document.getElementById('fotoPsicologoCortada');
    if (croppedPhoto) croppedPhoto.value = photoCropState.canvas.toDataURL('image/jpeg', .92);
}

function resetPhotoCropper() {
    const removed = document.getElementById('removerFoto')?.value === '1';
    if (removed) {
        photoCropState.hasSavedPhoto = false;
    } else if (photoCropState.image) {
        photoCropState.hasSavedPhoto = true;
    }

    photoCropState.image = null;
    photoCropState.dragging = false;

    const zoomInput = document.getElementById('photoZoom');
    if (zoomInput) {
        zoomInput.value = '1';
        zoomInput.disabled = true;
    }

    document.getElementById('photoZoomWrap')?.classList.add('d-none');

    const input = document.getElementById('fotoPsicologo');
    if (input) input.value = '';

    const croppedPhoto = document.getElementById('fotoPsicologoCortada');
    if (croppedPhoto) croppedPhoto.value = '';

    setRemovePhoto(false);
}

function setRemovePhoto(remove) {
    const field = document.getElementById('removerFoto');
    if (field) {
        field.value = remove ? '1' : '0';
        // Avisa o formulário (ex.: controle de alterações não salvas)
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    updateRemoveButton();
}

function updateRemoveButton() {
    const button = document.getElementById('btn-remove-photo');
    if (!button) return;

    const removed = document.getElementById('removerFoto')?.value === '1';
    button.classList.toggle('d-none', removed || !(photoCropState.hasSavedPhoto || photoCropState.image));
}

function confirmRemovePhoto() {
    confirmAction('Tem certeza que deseja <strong>remover</strong> a foto? A alteração será aplicada ao salvar as configurações.', function () {
        if (photoCropState.tainted) replacePhotoCanvas();
        resetPhotoCropper();
        setRemovePhoto(true);
        drawPhotoPlaceholder();
    });
}

function replacePhotoCanvas() {
    const fresh = photoCropState.canvas.cloneNode(false);
    photoCropState.canvas.replaceWith(fresh);
    photoCropState.canvas = fresh;
    photoCropState.context = fresh.getContext('2d');
    photoCropState.tainted = false;
}

function drawPhotoPlaceholder() {
    const { context, canvas } = photoCropState;
    context.fillStyle = '#e9ecef';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.fillStyle = '#6c757d';
    context.textAlign = 'center';
    context.font = '600 27px sans-serif';
    context.fillText('Selecione uma foto', canvas.width / 2, canvas.height / 2);
}

function drawPhoto() {
    const { context, canvas, image } = photoCropState;
    if (!image) return;

    const scale = Math.max(canvas.width / image.width, canvas.height / image.height) * photoCropState.zoom;
    const width = image.width * scale;
    const height = image.height * scale;
    const maxOffsetX = (width - canvas.width) / 2;
    const maxOffsetY = (height - canvas.height) / 2;
    photoCropState.offsetX = Math.min(maxOffsetX, Math.max(-maxOffsetX, photoCropState.offsetX));
    photoCropState.offsetY = Math.min(maxOffsetY, Math.max(-maxOffsetY, photoCropState.offsetY));

    const x = (canvas.width - width) / 2 + photoCropState.offsetX;
    const y = (canvas.height - height) / 2 + photoCropState.offsetY;

    context.fillStyle = '#fff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(image, x, y, width, height);
}

/**
 * Dias de atendimento, pausas, feriados e serviços: comportamento compartilhado
 * entre a tela de Configurações e o Onboarding (mesmos partials de settings/partials).
 * Cada função atua no <form> do elemento recebido, sem depender da tela.
 */

// Períodos por dia (ex.: manhã presencial, tarde online, noite online); o backend aceita o mesmo limite
const MAX_DAY_PERIODS = 3;

/**
 * Liga os switches de dias/pausas, o botão de copiar horário e a marcação de
 * intervalos inválidos do formulário.
 */
function bindScheduleSlots(selectorForm) {
    const $form = $(selectorForm);

    $form.on('change', '.day-toggle, .meal-toggle', function () {
        toggleScheduleSlot(this);
    });
    $form.find('.day-toggle, .meal-toggle').each(function () {
        toggleScheduleSlot(this);
    });

    $form.on('click', '.cfg-slot-copy', function () {
        copyDayHours(this);
    });

    $form.on('change', '.cfg-time', function () {
        const $times = $(this).closest('.cfg-period, .cfg-slot-times, .cfg-item-hours').find('.cfg-time');
        markTimeRange($times.eq(0), $times.eq(1));
    });

    $form.on('change', '.attendance-mode', function () {
        toggleDayModes($form);
    });
    toggleDayModes($form);

    $form.on('click', '.cfg-period-add', function () {
        addDayPeriod($(this).closest('.cfg-periods'));
        refreshScheduleFormDirty(this);
    });

    $form.on('click', '.cfg-period-remove', function () {
        const $periods = $(this).closest('.cfg-periods');
        $(this).closest('.cfg-period').remove();
        refreshPeriodAdd($periods);
        refreshScheduleFormDirty($periods[0]);
    });
}

/**
 * O formato e os períodos extras só valem com "online e presencial"; nas demais modalidades
 * eles somem e não são enviados (o dia fica com o primeiro período, na modalidade geral).
 */
function toggleDayModes($form) {
    const both = isBothAttendanceModes($form);

    $form.find('.cfg-day-mode').prop('hidden', !both).prop('disabled', !both);
    $form.find('.cfg-period.is-extra').prop('hidden', !both).find('input, select').prop('disabled', !both);
    $form.find('.cfg-periods').each(function () {
        refreshPeriodAdd($(this));
    });
}

function isBothAttendanceModes($form) {
    return $form.find('.attendance-mode:checked').val() === 'both';
}

/**
 * "Adicionar período" só aparece com "online e presencial" e enquanto o dia tem menos de MAX_DAY_PERIODS.
 */
function refreshPeriodAdd($periods) {
    const both = isBothAttendanceModes($periods.closest('form'));

    $periods.find('.cfg-period-add').prop('hidden', !both || $periods.find('.cfg-period').length >= MAX_DAY_PERIODS);
}

/**
 * Adiciona um período ao dia a partir do modelo #tpl-periodo. Sem horário informado,
 * começa no fim do último período e dura uma hora.
 */
function addDayPeriod($periods, period = {}) {
    const template = document.getElementById('tpl-periodo');
    if (!template || $periods.find('.cfg-period').length >= MAX_DAY_PERIODS) return;

    const html = template.innerHTML
        .replace(/__DAY__/g, $periods.data('day'))
        .replace(/__INDEX__/g, Date.now() + Math.floor(Math.random() * 1000))
        .replace(/__LABEL__/g, $periods.data('label'));
    const start = period.start || $periods.find('.cfg-period').last().find('input[name$="[end]"]').val() || '';
    const $period = $(html.trim()).insertBefore($periods.find('.cfg-period-add'));

    $period.find('input[name$="[start]"]').val(start);
    $period.find('input[name$="[end]"]').val(period.end || addOneHour(start));

    if (period.mode) {
        $period.find('.cfg-day-mode').val(period.mode);
    }

    refreshPeriodAdd($periods);
}

function addOneHour(time) {
    if (!time) return '';

    const [h, m] = time.split(':').map(Number);
    const minutes = Math.min(h * 60 + m + 60, 23 * 60 + 59);

    return String(Math.floor(minutes / 60)).padStart(2, '0') + ':' + String(minutes % 60).padStart(2, '0');
}

/**
 * Períodos visíveis do dia, na ordem da tela.
 */
function dayPeriods($day) {
    return $day.find('.cfg-period').not('[hidden]').map(function () {
        const $times = $(this).find('.cfg-time');

        return {
            start: $times.eq(0).val(),
            end: $times.eq(1).val(),
            mode: $(this).find('.cfg-day-mode').val() || '',
            $start: $times.eq(0),
            $end: $times.eq(1),
        };
    }).get();
}

function periodsKey(periods) {
    return periods.map(function (period) {
        return [period.start, period.end, period.mode].join('|');
    }).join(';');
}

/**
 * Substitui os períodos do dia pelos informados (o primeiro reaproveita a linha fixa do dia).
 */
function setDayPeriods($day, periods) {
    const $periods = $day.find('.cfg-periods');
    const $first = $periods.find('.cfg-period').first();

    $periods.find('.cfg-period.is-extra').remove();
    $first.find('input[name$="[start]"]').val(periods[0].start);
    $first.find('input[name$="[end]"]').val(periods[0].end);
    $first.find('.cfg-day-mode').val(periods[0].mode || 'both');

    periods.slice(1).forEach(function (period) {
        addDayPeriod($periods, period);
    });

    refreshPeriodAdd($periods);
    $periods.find('.cfg-time').trigger('change');
}

/**
 * O switch alterna entre os horários e "Sem atendimento".
 * Nos dias, os horários continuam sendo enviados (preserva o que foi digitado);
 * nas pausas, os campos ficam desabilitados.
 */
function toggleScheduleSlot(checkbox) {
    const $slot = $(checkbox).closest('.cfg-slot');
    const active = checkbox.checked;

    $slot.toggleClass('is-off', !active);

    if ($(checkbox).hasClass('meal-toggle')) {
        $slot.find('.cfg-slot-times input').prop('disabled', !active);
    }
}

/**
 * Copia os períodos do dia (horários e formatos) para os demais dias ativos e informa o resultado,
 * já que copiar um horário igual ao dos outros dias não muda nada na tela.
 */
function copyDayHours(button) {
    const $source = $(button).closest('.cfg-day');
    const label = $source.find('.cfg-slot-label').text().trim();
    const periods = dayPeriods($source);
    const $targets = $source.closest('form').find('.cfg-day').not('.is-off').not($source);

    if (!$targets.length) {
        appDialog({ tone: 'info', title: 'Nenhum outro dia ativo', text: 'Ative outros dias para copiar este horário.' });
        return;
    }

    const invalid = !periods.length || periods.some(function (period) {
        return !period.start || !period.end || !markTimeRange(period.$start, period.$end);
    });

    if (invalid) {
        clickWarningMessage('Confira os horários', 'Informe um horário válido em ' + label + ' antes de copiar.');
        return;
    }

    const key = periodsKey(periods);
    const text = periods.map(function (period) {
        return period.start + '–' + period.end;
    }).join(', ');

    const $changed = $targets.filter(function () {
        return periodsKey(dayPeriods($(this))) !== key;
    });

    if (!$changed.length) {
        showToast('info', 'Os outros dias ativos já estão com ' + text + '.');
        return;
    }

    $changed.each(function () {
        const $day = $(this);
        setDayPeriods($day, periods);

        $day.addClass('is-flash');
        setTimeout(function () { $day.removeClass('is-flash'); }, 1200);
    });

    const days = $changed.length === 1 ? '1 dia' : $changed.length + ' dias';
    showToast('success', 'Horário de ' + label + ' (' + text + ') copiado para ' + days + '.');
}

/**
 * Marca o fim como inválido quando não é depois do início. Retorna se o intervalo é válido.
 */
function markTimeRange($start, $end) {
    const valid = !$start.val() || !$end.val() || $start.val() < $end.val();
    $end.toggleClass('is-invalid', !valid);

    return valid;
}

/**
 * Confere os horários ativos (dias, pausas e feriados com atendimento) do formulário
 * e retorna os pares de campos com o fim antes do início.
 */
function findInvalidScheduleRanges($form) {
    const ranges = [];

    $form.find('.cfg-time').removeClass('is-invalid');

    $form.find('.cfg-slot').not('.is-off').each(function () {
        const $periods = $(this).find('.cfg-period').not('[hidden]');

        if (!$periods.length) {
            ranges.push($(this).find('.cfg-time'));
            return;
        }

        $periods.each(function () {
            ranges.push($(this).find('.cfg-time'));
        });
    });
    $form.find('.feriado-item').each(function () {
        if ($(this).find('input[name$="[work_enabled]"]').is(':checked')) {
            ranges.push($(this).find('.cfg-time'));
        }
    });

    return ranges.filter(function ($times) {
        return !markTimeRange($times.eq(0), $times.eq(1));
    });
}

function refreshScheduleFormDirty(element) {
    if (typeof refreshFormDirty === 'function') {
        refreshFormDirty(element ? element.closest('form') : null);
    }
}

function toggleHorarioFeriado(checkbox) {
    const feriadoItem = checkbox.closest('.feriado-item');
    if (!feriadoItem) return;

    const horariosDiv = feriadoItem.querySelector('.feriado-horarios');
    if (!horariosDiv) return;

    horariosDiv.classList.toggle('d-none', !checkbox.checked);
}

/**
 * Adiciona um item na lista de feriados a partir do modelo #tpl-feriado.
 * `holiday.date` deve estar em Y-m-d. Sem o date picker (tela de convidado), aplica a máscara de data.
 */
function addFeriado(holiday = {}, initPickers = true) {
    const container = document.getElementById('container-feriados');
    const template = document.getElementById('tpl-feriado');
    if (!container || !template) return;

    const id = Date.now() + Math.floor(Math.random() * 1000);
    container.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__ID__/g, id));

    const item = container.lastElementChild;
    item.classList.add('is-new');
    item.querySelector('input[name$="[date]"]').value = holiday.date ? holiday.date.split('-').reverse().join('/') : '';
    item.querySelector('input[name$="[name]"]').value = holiday.name || '';

    refreshFeriadosEmpty();

    if (initPickers) {
        initFeriadoDates();
    }

    refreshScheduleFormDirty(container);
}

function initFeriadoDates() {
    if (typeof initOnlyDatePickers === 'function') {
        initOnlyDatePickers();
        return;
    }

    maskDate();
}

/**
 * Remove um feriado da lista; os já salvos pedem confirmação. A remoção vale ao salvar.
 */
function removeFeriado(button) {
    const item = button.closest('.feriado-item');
    if (!item) return;

    const container = item.parentElement;
    const remove = function () {
        item.remove();
        refreshFeriadosEmpty();
        refreshScheduleFormDirty(container);
    };

    if (item.classList.contains('is-new')) {
        remove();
        return;
    }

    confirmAction('Remover este feriado? A remoção é aplicada ao <strong>salvar</strong>.', remove);
}

function refreshFeriadosEmpty() {
    $('#feriados-empty').toggleClass('d-none', $('#container-feriados .feriado-item').length > 0);
}

/**
 * Busca os feriados nacionais e adiciona à lista os que ainda não estão nela.
 * Nada é gravado até o usuário salvar.
 */
function importFeriadosNacionais(url, saveHint = 'Salve para confirmar.') {
    $.ajax({
        url: url,
        type: 'GET',
        dataType: 'json',
        beforeSend: loadingAjax,
        success: function (response) {
            closeLoadingAjax();

            const existing = $('#container-feriados input[name$="[date]"]').map(function () {
                return $(this).val();
            }).get();

            const added = (response.holidays || []).filter(function (holiday) {
                return !existing.includes(holiday.date.split('-').reverse().join('/'));
            });

            added.forEach(function (holiday) {
                addFeriado(holiday, false);
            });

            if (added.length) {
                initFeriadoDates();
            }

            appDialog({
                tone: added.length ? 'success' : 'info',
                title: added.length ? 'Feriados adicionados' : 'Nada novo por aqui',
                text: added.length
                    ? added.length + ' feriados nacionais adicionados. ' + saveHint
                    : 'Os feriados nacionais já estão na lista.',
            });
        },
        error: function (jqXHR) {
            closeLoadingAjax();
            errorResponseJson(jqXHR, '');
        },
    });
}

/**
 * Adiciona um serviço a partir do modelo #tpl-servico.
 */
function addServico() {
    const container = document.getElementById('container-servicos');
    const template = document.getElementById('tpl-servico');
    if (!container || !template) return;

    container.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, Date.now()));
    container.lastElementChild.querySelector('input[name$="[name]"]').focus();

    refreshScheduleFormDirty(container);
}

/**
 * Remove um serviço da lista; os já salvos pedem confirmação. A remoção vale ao salvar.
 */
function removeServico(button) {
    const item = button.closest('.servico-item');
    if (!item) return;

    const container = item.parentElement;
    const remove = function () {
        item.remove();
        refreshScheduleFormDirty(container);
    };

    if (!item.querySelector('input[name$="[id]"]').value) {
        remove();
        return;
    }

    confirmAction('Remover este serviço? A remoção é aplicada ao <strong>salvar</strong>.', remove);
}

/**
 * Ao terminar de digitar o CEP (8 dígitos) busca o endereço em /zipcode/{cep}
 * e preenche os campos; ao apagar o CEP, limpa todos os campos de endereço.
 * O botão (lupa) permite fazer a mesma busca manualmente. No onboarding a busca
 * usa a rota própria (`url`), já que /zipcode exige o onboarding concluído.
 */
function bindZipcodeLookup(selectorForm, fields, url = '/zipcode/') {
    if (!elementExists(selectorForm) || !elementExists(fields.zipcode)) return;

    const $form = $(selectorForm);
    let lastZipcode = '';

    const clearAddress = function () {
        $form.find([fields.city, fields.neighborhood, fields.street, fields.complement, fields.number].join(',')).val('');
    };

    $form.find(fields.zipcode).on('input', function () {
        const zipcode = $(this).val().replace(/\D/g, '');

        if (zipcode.length === 0) {
            lastZipcode = '';
            clearAddress();
            return;
        }

        if (zipcode.length < 8) {
            lastZipcode = '';
            return;
        }

        if (zipcode === lastZipcode) return;
        lastZipcode = zipcode;

        fillAddress(zipcode);
    });

    if (fields.button && elementExists(fields.button)) {
        $form.find(fields.button).on('click', function () {
            const zipcode = $form.find(fields.zipcode).val().replace(/\D/g, '');

            if (zipcode.length !== 8) {
                clickWarningMessage('CEP incompleto', 'Informe um CEP válido, com 8 dígitos.');
                return;
            }

            lastZipcode = zipcode;
            fillAddress(zipcode);
        });
    }

    function fillAddress(zipcode) {
        // Troca a lupa por um loading enquanto a busca está em andamento
        const $icon = fields.button ? $form.find(fields.button).find('i') : $();
        $icon.removeClass('fa-search').addClass('fa-spinner fa-spin');

        searchZipcode(zipcode, function (address) {
            // Ignora resposta atrasada se o CEP mudou durante a requisição
            if ($form.find(fields.zipcode).val().replace(/\D/g, '') !== zipcode) return;

            $form.find(fields.city).val(address.city);
            $form.find(fields.neighborhood).val(address.neighborhood);
            $form.find(fields.street).val(address.street);
            // Complemento e número são informados pelo usuário: sempre limpos a cada busca
            $form.find(fields.complement).val('');
            $form.find(fields.number).val('').trigger('focus');
        }, function () {
            $icon.removeClass('fa-spinner fa-spin').addClass('fa-search');
        }, url);
    }
}

function searchZipcode(zipcode, callbackSuccess, callbackComplete = null, url = '/zipcode/') {
    $.ajax({
        url: url + zipcode,
        type: 'GET',
        dataType: 'json',
        success: callbackSuccess,
        complete: callbackComplete,
        error: function (jqXHR) {
            if (isSessionExpired(jqXHR)) return;

            const message = jqXHR.responseJSON && jqXHR.responseJSON.message
                ? jqXHR.responseJSON.message
                : 'Não foi possível buscar o CEP. Tente novamente.';

            clickWarningMessage('CEP não encontrado', message);
        },
    });
}

const ONBOARDING_HELP_DELAY = 30000;
const ONBOARDING_TOTAL_STEPS = 3;
const ONBOARDING_STEP_DONE = 4;
const ONBOARDING_AGENDA_SUBSTEPS = 4;
const ONBOARDING_PREVIEW_SLOTS = 4;

// Tela atual: etapa, passo da agenda e posição no histórico do navegador
let onboardingState = { step: 1, substep: 1, index: 0 };

$(document).ready(function () {
    if (!elementExists('.ob-wrapper')) return;

    bindOnboardingAgenda();
    bindOnboardingPsychologist();
    bindOnboardingBooking();
    bindOnboardingHelp();
    bindOnboardingHistory();
    setupPhotoCropper(window.onboardingPhotoUrl || null);

    const step = parseInt($('.ob-wrapper').data('step'), 10) || 1;
    onboardingState = { step: step, substep: 1, index: 0 };
    history.replaceState({ onboarding: onboardingState }, '');
    renderOnboarding(onboardingState, false);
});

/**
 * Cada tela (etapa e passo da agenda) vira uma entrada no histórico: o "voltar" do
 * celular recua uma tela em vez de sair do onboarding.
 */
function bindOnboardingHistory() {
    window.addEventListener('popstate', function (e) {
        const state = e.state && e.state.onboarding;
        if (!state) return;

        // Concluído não volta para as etapas; avançar pelo navegador não pula o salvamento da etapa
        const done = onboardingState.step === ONBOARDING_STEP_DONE;
        const skipsSave = state.index > onboardingState.index && state.step > onboardingState.step;

        if (done || skipsSave) {
            history.pushState({ onboarding: onboardingState }, '');
            return;
        }

        onboardingState = state;
        renderOnboarding(state, true);
    });
}

function navigateOnboarding(step, substep = 1) {
    onboardingState = { step: step, substep: substep, index: onboardingState.index + 1 };
    history.pushState({ onboarding: onboardingState }, '');
    renderOnboarding(onboardingState, true);
}

/**
 * "Voltar" da tela: usa o histórico quando a tela anterior está nele; ao abrir o onboarding
 * já numa etapa adiante, não há tela anterior e a troca é feita no lugar.
 */
function backOnboarding(step, substep = 1) {
    if (onboardingState.index > 0) {
        history.back();
        return;
    }

    onboardingState = { step: step, substep: substep, index: 0 };
    history.replaceState({ onboarding: onboardingState }, '');
    renderOnboarding(onboardingState, true);
}

function renderOnboarding(state, focus) {
    showOnboardingStep(state.step);

    if (state.step === 1) {
        showOnboardingSubstep(state.substep, focus);
    }
}

function showOnboardingSubstep(substep, focus) {
    const $substep = $('#form-onboarding-agenda .ob-substep[data-substep="' + substep + '"]');

    $('#form-onboarding-agenda .ob-substep').removeClass('active');
    $substep.addClass('active');

    $('#ob-progress-label').text($substep.data('label'));
    $('#ob-progress-count').text(substep + ' de ' + ONBOARDING_AGENDA_SUBSTEPS);
    $('#ob-progress-fill').css('width', (substep / ONBOARDING_AGENDA_SUBSTEPS * 100) + '%');
    $('#ob-progress-bar').attr('aria-valuenow', substep);
    $('#btn-onboarding-agenda-back').toggleClass('d-none', substep === 1);

    document.title = $substep.data('label') + ' · Etapa 1 de ' + ONBOARDING_TOTAL_STEPS + ' · Configuração inicial';

    // O horário inicial da prévia vem da grade do último passo, que pode ter mudado
    if (substep === 2) {
        refreshOnboardingPreview();
    }

    // Leitor de tela anuncia a nova pergunta; o scroll ao topo fica com showOnboardingStep
    const title = $substep.find('.ob-substep-title').get(0);
    if (focus && title) {
        title.focus({ preventScroll: true });
    }
}

function showOnboardingStep(step) {
    $('.ob-step').removeClass('active');
    $('.ob-step[data-step="' + step + '"]').addClass('active');

    $('#ob-steps .ob-steps-item').each(function () {
        const dot = parseInt($(this).data('dot'), 10);
        $(this)
            .toggleClass('is-active', dot === step)
            .toggleClass('is-done', dot < step)
            .attr('aria-current', dot === step ? 'step' : null);
    });

    if (step <= ONBOARDING_TOTAL_STEPS) {
        document.title = 'Etapa ' + step + ' de ' + ONBOARDING_TOTAL_STEPS + ' · Configuração inicial';
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function bindOnboardingAgenda() {
    const selectorForm = '#form-onboarding-agenda';

    bindScheduleSlots(selectorForm);
    bindOnboardingDefaultHours(selectorForm);
    maskDate();

    $(selectorForm).on('change', '.meal-toggle', refreshOnboardingOptionalSummary);
    new MutationObserver(refreshOnboardingOptionalSummary)
        .observe(document.getElementById('container-feriados'), { childList: true });
    refreshOnboardingOptionalSummary();

    $(selectorForm).on('change', 'input[name="consultation_time"], input[name="available_time_minutes"]', refreshOnboardingPreview);
    refreshOnboardingPreview();

    $(selectorForm).on('change', '.day-toggle', refreshOnboardingCopyFirst);
    refreshOnboardingCopyFirst();
    $('#btn-copy-first-day').on('click', function () {
        $(selectorForm).find('.cfg-day').not('.is-off').first().find('.cfg-slot-copy').trigger('click');
    });

    $('#btn-import-holidays').on('click', function () {
        importFeriadosNacionais('/onboarding/national-holidays', 'Eles são salvos ao continuar.');
    });

    $('#btn-onboarding-agenda-back').on('click', function () {
        backOnboarding(1, Math.max(onboardingState.substep - 1, 1));
    });

    // Enviar o form avança um passo; só o último salva a agenda
    $(selectorForm).submit(function (e) {
        e.preventDefault();

        if (onboardingState.substep < ONBOARDING_AGENDA_SUBSTEPS) {
            navigateOnboarding(1, onboardingState.substep + 1);
            return;
        }

        if (!validateOnboardingAgendaHours()) return;

        sendRequest('/onboarding/agenda', 'POST', selectorForm, 'json', function () {
            navigateOnboarding(2);
        }, true, function (jqXHR) {
            errorResponseJson(jqXHR, selectorForm);
            showOnboardingSubstepWithError();
            openOnboardingOptionalIfInvalid();
        });
    });
}

/**
 * Erro devolvido pelo backend em campo de outro passo: leva até o passo do primeiro campo inválido.
 */
function showOnboardingSubstepWithError() {
    const substep = parseInt($('#form-onboarding-agenda .is-invalid').first().closest('.ob-substep').data('substep'), 10);

    if (substep && substep !== onboardingState.substep) {
        navigateOnboarding(1, substep);
    }
}

/**
 * Prévia no passo do intervalo: os primeiros horários do dia com a duração e o intervalo escolhidos.
 */
function refreshOnboardingPreview() {
    const $form = $('#form-onboarding-agenda');
    const $duration = $form.find('input[name="consultation_time"]:checked');
    const $interval = $form.find('input[name="available_time_minutes"]:checked');

    if (!$duration.length || !$interval.length) return;

    const duration = onboardingMinutes($duration.val());
    const interval = onboardingMinutes($interval.val());
    const start = onboardingMinutes($form.find('.cfg-day').not('.is-off').first().find('.cfg-time').first().val() || '09:00');
    const durationLabel = $form.find('label[for="' + $duration.attr('id') + '"]').text().trim();
    const intervalLabel = $form.find('label[for="' + $interval.attr('id') + '"]').text().trim();

    const times = [];
    for (let i = 0; i < ONBOARDING_PREVIEW_SLOTS; i++) {
        const minutes = start + i * (duration + interval);
        if (minutes >= 24 * 60) break;

        times.push($('<span>').text(onboardingTime(minutes)));
    }

    $('#ob-preview-text').text('Sessões de ' + durationLabel + (interval ? ' com ' + intervalLabel + ' de intervalo' : ', sem intervalo') + ': suas consultas começariam às');
    $('#ob-preview-times').empty().append(times);
}

function onboardingMinutes(time) {
    const [h, m] = String(time).split(':').map(Number);

    return (h || 0) * 60 + (m || 0);
}

function onboardingTime(minutes) {
    return String(Math.floor(minutes / 60)).padStart(2, '0') + ':' + String(minutes % 60).padStart(2, '0');
}

/**
 * Atalho para copiar o horário do primeiro dia ativo para os demais (só com dois ou mais dias ativos).
 */
function refreshOnboardingCopyFirst() {
    const $active = $('#form-onboarding-agenda .cfg-day').not('.is-off');

    $('#btn-copy-first-day').toggleClass('d-none', $active.length < 2);
    $('#ob-copy-first-label').text($active.first().find('.cfg-slot-label').text().trim().toLowerCase());
}

/**
 * Sugere os horários da modalidade escolhida (data-default-hours). Só reaplica enquanto a
 * psicóloga não editou a grade; com agenda já salva, só sobrescreve se a grade salva
 * ainda é a sugestão da modalidade salva (ela voltou à Agenda sem ter personalizado nada).
 */
function bindOnboardingDefaultHours(selectorForm) {
    const $form = $(selectorForm);
    const defaults = $form.data('default-hours');

    if (!defaults) return;

    let touched = parseInt($form.data('saved'), 10) === 1
        && !isOnboardingSuggestedGrid($form, defaults[$form.find('.attendance-mode:checked').val()]);

    // originalEvent só existe em ação do usuário; os trigger() internos não contam como edição
    $form.on('change', '.cfg-time, .cfg-day-mode', function (e) {
        if (e.originalEvent) touched = true;
    });
    $form.on('click', '.cfg-period-add, .cfg-period-remove, .cfg-slot-copy', function () {
        touched = true;
    });

    $form.on('change', '.attendance-mode', function () {
        const periods = defaults[$(this).val()];

        if (touched || !periods) return;

        $form.find('.cfg-day').each(function () {
            setDayPeriods($(this), periods);
        });
        toggleDayModes($form);
    });
}

/**
 * Os dias ativos estão com exatamente os períodos sugeridos? O formato de cada período
 * só conta com "online e presencial" (nas demais modalidades ele não é salvo).
 */
function isOnboardingSuggestedGrid($form, suggested) {
    if (!suggested) return false;

    const both = isBothAttendanceModes($form);
    const key = function (periods) {
        return periods.map(function (period) {
            return period.start + '-' + period.end + (both ? '|' + (period.mode || 'both') : '');
        }).join(';');
    };
    const expected = key(suggested);

    return $form.find('.cfg-day').not('.is-off').toArray().every(function (day) {
        return key(dayPeriods($(day))) === expected;
    });
}

/**
 * Antes de salvar, confere se o fim de cada horário ativo é depois do início;
 * se o problema estiver nas pausas/feriados, abre o bloco recolhido.
 */
function validateOnboardingAgendaHours() {
    const invalid = findInvalidScheduleRanges($('#form-onboarding-agenda'));

    if (!invalid.length) return true;

    openOnboardingOptionalIfInvalid();
    invalid[0].eq(1).trigger('focus');
    clickWarningMessage('Confira os horários', 'O horário de término precisa ser depois do horário de início.');

    return false;
}

function openOnboardingOptionalIfInvalid() {
    if (!$('#ob-optional-body .is-invalid').length) return;

    bootstrap.Collapse.getOrCreateInstance(document.getElementById('ob-optional-body'), { toggle: false }).show();
}

/**
 * Resumo exibido no bloco recolhido: pausas ativas e quantidade de feriados.
 */
function refreshOnboardingOptionalSummary() {
    const meals = $('#form-onboarding-agenda .meal-toggle:checked').map(function () {
        return $(this).data('label');
    }).get();
    const holidays = $('#container-feriados .feriado-item').length;

    const parts = [meals.length ? 'Pausa: ' + meals.join(' e ') : 'Sem pausas'];
    parts.push(holidays ? holidays + (holidays === 1 ? ' feriado' : ' feriados') : 'nenhum feriado');

    $('#ob-optional-summary').text(parts.join(' · '));
}

function bindOnboardingPsychologist() {
    $('#btn-onboarding-back').on('click', function () {
        backOnboarding(1, ONBOARDING_AGENDA_SUBSTEPS);
    });

    bindZipcodeLookup('#form-onboarding-psychologist', {
        zipcode: '#cep',
        city: '#cidade',
        neighborhood: '#bairro',
        street: '#rua',
        complement: '#complemento',
        number: '#numero',
        button: '#btn-search-zipcode',
    }, '/onboarding/zipcode/');

    $('#form-onboarding-agenda').on('change', '.attendance-mode', refreshOnboardingAddress);
    refreshOnboardingAddress();

    $('#form-onboarding-psychologist').submit(function (e) {
        e.preventDefault();

        prepareCroppedPhoto();

        sendRequest('/onboarding/psychologist', 'POST', '#form-onboarding-psychologist', 'json', function () {
            navigateOnboarding(3);
        });
    });
}

/**
 * Endereço do consultório só aparece (e é obrigatório) para quem escolheu atender
 * presencialmente na etapa 1; o backend aplica a mesma regra.
 */
function refreshOnboardingAddress() {
    if (!elementExists('#ob-address')) return;

    const mode = $('#form-onboarding-agenda .attendance-mode:checked').val();
    const inPerson = mode === 'in_person' || mode === 'both';

    $('#ob-address').toggleClass('d-none', !inPerson);
    $('#ob-address-hint').toggleClass('d-none', !inPerson);
    $('#ob-address').find('#cidade, #rua, #numero').prop('required', inPerson);
}

function bindOnboardingBooking() {
    bindSlugAvailability('#slug', '#btn-check-slug', '#slug-availability', '/onboarding/slug-availability');

    $('#slug').on('input', function () {
        this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-{2,}/g, '-');
        $('#slug-availability').addClass('d-none').empty();
        refreshOnboardingLinkPreview();
    });
    refreshOnboardingLinkPreview();

    $('#container-servicos').on('focus', '.money-service', function () {
        $(this).mask('#.##0,00', { reverse: true });
    });

    $('#btn-onboarding-back-booking').on('click', function () {
        backOnboarding(2);
    });

    $('#btn-copy-booking-link').on('click', function () {
        copyToClipboard(getOnboardingBookingLink(), 'Link copiado');
    });

    $('#form-onboarding-booking').submit(function (e) {
        e.preventDefault();

        sendRequest('/onboarding/booking', 'POST', '#form-onboarding-booking', 'json', function (response) {
            showOnboardingDone(response.link);
        });
    });
}

function getOnboardingBookingLink() {
    return $('#ob-link-preview').data('base') + $('#slug').val().trim();
}

function refreshOnboardingLinkPreview() {
    const slug = $('#slug').val().trim();

    $('#ob-link-preview').text(slug ? getOnboardingBookingLink() : 'Informe o endereço da sua página');
}

/**
 * Onboarding concluído: mostra o link da página de agendamento antes de ir ao painel.
 */
function showOnboardingDone(dashboardLink) {
    const link = getOnboardingBookingLink();

    $('#ob-done-url').attr('href', link).text(link);
    $('#ob-done-dashboard').attr('href', dashboardLink);
    $('#ob-help').removeClass('show');

    navigateOnboarding(ONBOARDING_STEP_DONE);
}

function bindOnboardingHelp() {
    setTimeout(function () {
        if ($('#step-done').hasClass('active')) return;

        $('#ob-help').addClass('show');
    }, ONBOARDING_HELP_DELAY);

    $('#ob-help-close').on('click', function () {
        $('#ob-help').removeClass('show');
    });
}
