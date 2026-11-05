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
