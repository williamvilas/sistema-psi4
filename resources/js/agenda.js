function getPatientsByAccount(json) {

    $('.row-exist-patient').html('');

    sendRequestByJson(
        '/patients/exists',
        'GET',
        json,
        function(response) {
            let selectorIdentPatient = '.row-identifier-patient';
            $(selectorIdentPatient).hide();

            $('.row-patient').html(response.html);

            maskWhatsApp();
            maksCPF();
            maskDate();

            if (!response.exists) {
                $(selectorIdentPatient).remove();

                const phoneInput = document.getElementById('phone');
                phoneInput.addEventListener('input', function () {

                    let value = this.value.replace(/\D/g, '');

                    if (value.length === 11) {
                        getPatientsByAccount({ phone: value });
                    }
                });

                $('#nextBtn').attr('onclick', 'insertPatientByAgenda()');
            } else {
                $('#nextBtn').attr('onclick', 'nextStepWizardAgenda()');
            }

            if (response.isToday) {
                setTimeout( createConfettiInPatient, 500);
                setInterval(createConfettiInPatient, 5000);
            }
        }
    );
}

function getAgendaAvailable() {
    $('.row-available').html('');

    sendRequestByJson(
        '/agenda/available',
        'GET',
        {
            date: $('#date').val()
        },
        function (response) {
            $('.row-available').html(response.html);
            renderAgendaTypeOptions(response.data.types || ['online']);
            //nextStepWizardAgenda();
            //$('#nextBtn').attr('onclick', 'nextStepWizardAgenda()');
        },
        function(jqXHR) {
            $('.date_only').data('daterangepicker').hide();
            errorResponseJson(jqXHR, '#form-store-agenda');
        }
    );
}

/**
 * Mostra só as modalidades aceitas na data; com uma só, ela já vem marcada e a escolha fica oculta.
 */
function renderAgendaTypeOptions(types) {
    $('input[name="type"]').each(function () {
        const allowed = types.includes(this.value);
        $(this).prop('disabled', !allowed).next('label').toggleClass('d-none', !allowed);
    });

    if (!$('input[name="type"]:checked:enabled').length) {
        $('input[name="type"][value="' + types[0] + '"]').prop('checked', true);
    }

    $('#type-options').toggleClass('d-none', types.length < 2);
    toggleAgendaLinkField();
    filterAgendaTimersByType();
}

function toggleAgendaLinkField() {
    const online = $('input[name="type"]:checked').val() === 'online';

    $('#link-field, .wizard-link-row').toggleClass('d-none', !online);
    $('#link').prop('disabled', !online);
}

/**
 * Cada período do dia tem sua modalidade: mostra só os horários da modalidade escolhida
 * e desmarca os que deixaram de valer.
 */
function filterAgendaTimersByType() {
    if (!elementExists('.available-timer')) return;

    const type = $('input[name="type"]:checked').val();
    let visible = 0;

    $('.available-timer').each(function () {
        const allowed = String($(this).data('types')).split(' ').includes(type);
        $(this).toggleClass('d-none', !allowed);

        if (!allowed) {
            $(this).find('input[name="timer[]"]').prop('checked', false);
        } else {
            visible++;
        }
    });

    $('.available-timer-empty').toggleClass('d-none', visible > 0);
}

$(document).on('change', 'input[name="type"]', function () {
    toggleAgendaLinkField();
    filterAgendaTimersByType();
});

function insertPatientByAgenda() {
    sendRequestByJson(
        '/patients',
        'POST',
        {
            name: $('#name').val().trim(),
            phone: $('#phone').val().trim(),
            birth_date: $('#birth_date').val().trim(),
            document: $('#document').val().trim(),
            no_document: $('#no_document').is(':checked') ? 1 : 0,
            email: $('#email').val().trim(),
            notes: $('#notes').val().trim(),
        },
        function (response) {
            $('#patient_id').val(response.data.id);
            nextStepWizardAgenda();
            $('#nextBtn').attr('onclick', 'nextStepWizardAgenda()');
        },
        function(jqXHR) {
            errorResponseJson(jqXHR, '#form-store-agenda');
        }
    );
}

function getSteepCurrentAgenda() {
    return $('#wizardSteps .nav-link.active').data('step');
}

function getTotalSteepAgenda() {
    return 3;
}

function previousWizardAgenda() {
    let currentStep = getSteepCurrentAgenda();

    if (currentStep > 1) {
        currentStep--;
        showStep(currentStep);
    }
}

function nextStepWizardAgenda() {

    let totalSteps = getTotalSteepAgenda();
    let currentStep = getSteepCurrentAgenda();

    if (!validateNextStepAgenda(currentStep)) {
        return;
    }

    if (currentStep < totalSteps) {
        currentStep++;
        showStep(currentStep);
    }

    if (currentStep === totalSteps) {
        buildWizardConfirmation();
    }
}

function buildWizardConfirmation() {
    let link = $('#link').val();
    link = link ? '<a href="'+link+'" target="_blank">'+link+'</a>' : '-';

    let timer = getTimerAgendaSelected();

    $('.text-name-wizard').text($('.text-patient-name').text());
    $('.text-phone-wizard').text($('.text-patient-phone').text());
    $('.text-date-wizard').text($('#date').val());
    $('.text-timer-wizard').text(timer.join(', '));
    $('.text-link-wizard').html(link);
    $('.text-type-wizard').text($('input[name="type"]:checked').next('label').text().trim());
}

function getTimerAgendaSelected() {
    return $('input[name="timer[]"]:checked')
        .map(function() {
            return this.value;
        })
        .get();
}

function finalizeAgenda() {
    confirmAction('Você está prestes a <strong>confirmar o agendamento</strong> deste paciente. Deseja realmente reservar este horário na sua agenda?', function() {
        storeAgenda();
    });
}

function storeAgenda() {

    sendRequest(
        '/agenda',
        'POST',
        '#form-store-agenda',
        'json',
        function (response) {
            return redirectTo(response.link);
        }
    )
}

function paginateAgenda(callback = null) {
    const form = '#form-search';
    totalFilters(form);
    $('#filterModal').modal('hide');

    sendRequest(
        '/agenda/paginate',
        'GET',
        form,
        'json',
        function(response) {
            $('.list-paginate').html(response.html);
            syncAgendaStatusFilter();
            if (isFunction(callback)) {
                callback();
            }
        }
    );
}

function dismissGoogleCalendarHero() {
    const selector = '#google-calendar-hero';
    if (!elementExists(selector)) return;

    sendRequestByJson(
        '/agenda/google-calendar-hero/dismiss',
        'PATCH',
        {},
        function (response) {
            $(selector).remove();
            $('.agenda-listing').removeClass('d-none');
            // o loading da paginação também usa Swal; o aviso só abre depois que a lista carregar
            paginateAgenda(function () {
                successResponseJson(response.title, response.message);
            });
        }
    );
}

function filterAgendaByStatus(status) {
    $('#form-search [name="pending_attendance"]').val('');
    $('#form-search [name="status"]').val(status || '');
    paginateAgenda();
}

function filterAgendaByPendingAttendance() {
    $('#form-search [name="status"]').val('');
    $('#form-search [name="pending_attendance"]').val('1');
    paginateAgenda();
}

function syncAgendaStatusFilter() {
    const status = $('#form-search [name="status"]').val() || '';
    const pendingAttendance = $('#form-search [name="pending_attendance"]').val() === '1';

    $('.dash-filter-chip').removeClass('active').filter(function () {
        if (pendingAttendance) {
            return $(this).attr('data-pending-attendance') === '1';
        }

        return $(this).attr('data-status') === status;
    }).addClass('active');
}

function clearFiltersAgenda() {
    clearForm();
    $('#form-search #patient_id').val(null).trigger('change');
    paginateAgenda();
}

function validateNextStepAgenda(stepCurrent) {

    if (stepCurrent === 1 && $('#patient_id').val()) {
        return true;
    } else if (stepCurrent === 2 && $('#date').val() && getTimerAgendaSelected().length > 0) {
        return true;
    }

    return false;
}

function showStep(step) {

    const totalSteps = getTotalSteepAgenda();
    const $steps = $('.wizard-step');
    const $navLinks = $('#wizardSteps .nav-link');

    const $prevBtn = $('#prevBtn');
    const $nextBtn = $('#nextBtn');
    const $submitBtn = $('#submitBtn');

    $steps.addClass('d-none');
    $(`.wizard-step[data-step="${step}"]`).removeClass('d-none');

    $navLinks.removeClass('active');
    $(`#wizardSteps .nav-link[data-step="${step}"]`).addClass('active');

    $prevBtn.toggleClass('d-none', step === 1);
    $nextBtn.toggleClass('d-none', step === totalSteps);
    $submitBtn.toggleClass('d-none', step !== totalSteps);
}

function existsPatientCreated() {
    var selector = '#patient_id';

    if ($(selector).val() && $(selector).val().length > 1) {
        return true;
    }

    return false;
}

function validateInsertPatientByAgenda() {

    const messageRequired = 'Este campo é obrigatório';
    const selectorForm = '#form-store-agenda';
    const inputName = '#name';
    const inputPhone = '#phone';
    const inputBirth = '#birth_date';
    const inputEmail = '#email';
    const inputNotes = '#notes';

    clearFormError(selectorForm);

    if (! $(inputName).val().trim()) {
        displayMessageErrorInput(
            selectorForm,
            inputName,
            messageRequired
        );
    }

    if (! $(inputPhone).val().trim()) {
        displayMessageErrorInput(
            selectorForm,
            inputPhone,
            messageRequired
        );
    }
}


function cancelAgenda(id, name) {
    const safeName = $('<div>').text(name).html();

    confirmAction('Tem certeza que deseja <strong>cancelar</strong> o agendamento de <strong>' + safeName + '</strong>? O horário ficará disponível novamente.', function() {
        sendRequestByJson(
            '/agenda/cancel',
            'PATCH',
            { id: id },
            function(response) {
                paginateAgenda(function() {
                    successResponseJson(response.title, response.message);
                });
            }
        );
    }, 'danger', 'Cancelar agendamento', 'Voltar');
}

function confirmAttendance(id, status, name) {
    const safeName = $('<div>').text(name).html();
    const action = status === 'completed' ? '<strong>compareceu</strong> à' : '<strong>faltou</strong> na';

    confirmAction('Confirma que <strong>' + safeName + '</strong> ' + action + ' sessão?', function() {
        updateAttendance(id, status);
    });
}

function changeAttendance(id, status, name) {
    const safeName = $('<div>').text(name).html();
    const newStatus = status === 'completed' ? '<strong>compareceu</strong>' : '<strong>falta</strong>';

    confirmAction('Deseja alterar a situação desta sessão de <strong>' + safeName + '</strong> para ' + newStatus + '?', function() {
        updateAttendance(id, status);
    }, 'warning', 'Sim', 'Não');
}

function updateAttendance(id, status) {
    sendRequestByJson(
        '/agenda/attendance',
        'PATCH',
        { id: id, status: status },
        function(response) {
            paginateAgenda(function() {
                successResponseJson(response.title, response.message);
            });
        }
    );
}

/**
 * Link da página pública na Agenda: mostra "Compartilhar" só onde o aparelho suporta (celular).
 */
function initBookingLinkShare() {
    if (!elementExists('.agenda-share-native') || typeof navigator.share !== 'function') return;

    $('.agenda-share-native').removeClass('d-none');
}

function copyBookingLink(button) {
    const url = $(button).closest('.agenda-share').data('url');
    if (!url) return;

    copyToClipboard(url, 'Link copiado', function () {
        const $button = $(button);
        const $label = $button.find('span');
        const $icon = $button.find('i');

        clearTimeout($button.data('copiedTimer'));
        $button.addClass('is-copied');
        $icon.attr('class', 'fa-solid fa-check me-2');
        $label.text('Copiado!');

        $button.data('copiedTimer', setTimeout(function () {
            $button.removeClass('is-copied');
            $icon.attr('class', 'fa-regular fa-copy me-2');
            $label.text('Copiar link');
        }, 2000));
    });
}

async function shareBookingLink(button) {
    const url = $(button).closest('.agenda-share').data('url');
    if (!url) return;

    try {
        await navigator.share({ title: 'Agende sua sessão', url: url });
    } catch (e) {
        // Fechar a janela de compartilhamento não é erro; nas demais falhas, copia o link
        if (e.name !== 'AbortError') {
            copyBookingLink($(button).siblings('.agenda-share-copy')[0]);
        }
    }
}
