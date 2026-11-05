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
