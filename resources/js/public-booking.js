$(document).ready(function () {
    const form = '#form-public-booking';

    if (!elementExists(form)) return;

    const slug = $(form).data('slug');
    const weekdays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    const months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    const typeLabels = { online: 'Online', in_person: 'Presencial' };
    const typeIcons = { online: 'bi-camera-video', in_person: 'bi-geo-alt' };
    let days = [];
    // Modalidades com horário em algum dia listado: com as duas, o seletor fica sempre visível
    let offeredTypes = [];
    let selectedDay = null;
    // Horários recusados pelo servidor nesta sessão: não voltam a ser oferecidos mesmo que a listagem ainda os traga
    const rejected = {};
    let current = 0;
    const labels = {};
    const steps = $(form).find('.step').toArray().map(function (el) {
        return $(el).data('step');
    });

    steps.forEach(function () {
        $('#progresso').append('<span></span>');
    });

    loadAvailability();
    goTo(0);

    $(form).submit(function (e) {
        e.preventDefault();
        storePublicBooking();
    });

    function loadAvailability(onLoaded) {
        $('#date, #time, #type').val('');
        $('#horarios').html('');
        $('#horariosWrap, #modalidadeWrap').addClass('d-none');
        selectedDay = null;

        $.ajax({
            url: '/lp/' + slug + '/availability',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                // slots vem agrupado por modalidade ({ online: [...], in_person: [...] }): cada período do dia tem a sua
                days = response.days.map(function (item) {
                    const slots = {};

                    (item.types || []).forEach(function (type) {
                        const times = (item.slots[type] || []).filter(function (time) {
                            return !rejected[item.date + ' ' + time];
                        });

                        if (times.length) slots[type] = times;
                    });

                    return { date: item.date, types: Object.keys(slots), slots: slots };
                }).filter(function (item) {
                    return item.types.length > 0;
                });
                offeredTypes = Object.keys(typeLabels).filter(function (type) {
                    return days.some(function (item) {
                        return item.types.includes(type);
                    });
                });
                renderDays();

                if (onLoaded) onLoaded();
            },
            error: function (jqXHR) {
                errorResponseJson(jqXHR, form);
            },
        });
    }

    function renderDays() {
        const container = $('#dias').html('');
        $('#diasVazio').toggleClass('d-none', days.length > 0);

        days.forEach(function (item) {
            const d = new Date(item.date + 'T00:00:00');
            const btn = $('<button type="button" class="dia-btn"></button>')
                .html(
                    '<span class="dw">' + weekdays[d.getDay()] + '</span>' +
                    '<span class="dd">' + String(d.getDate()).padStart(2, '0') + '</span>' +
                    '<span class="dm">' + months[d.getMonth()] + '</span>'
                )
                .on('click', function () {
                    $('.dia-btn').removeClass('active');
                    $(this).addClass('active');
                    $('#date').val(item.date);
                    $('#time').val('');
                    selectedDay = item;
                    renderTypes(item.types);
                });

            container.append(btn);
        });
    }

    /**
     * Dia com uma só modalidade já a define; com as duas, o paciente escolhe antes do horário.
     * Se a psicóloga atende nas duas, o seletor continua visível e a modalidade ausente no dia fica desabilitada.
     */
    function renderTypes(types) {
        const $wrap = $('#modalidadeWrap');
        const keep = types.includes($('#type').val()) ? $('#type').val() : null;

        $('#type').val(types.length === 1 ? types[0] : keep || '');
        $wrap.toggleClass('d-none', offeredTypes.length < 2);
        $wrap.find('.modalidade-btn').each(function () {
            const type = $(this).data('type');
            const selected = type === $('#type').val();

            $(this).prop('disabled', !types.includes(type))
                .toggleClass('active', selected)
                .attr('aria-pressed', selected ? 'true' : 'false');
        });

        // Só é exibida com o seletor visível, ou seja, quando a outra modalidade existe em outros dias
        const other = offeredTypes.find(function (type) {
            return !types.includes(type);
        });

        $('#modalidadeHint')
            .toggleClass('d-none', !other)
            .text(other
                ? 'Neste dia, apenas atendimento ' + typeLabels[types[0]].toLowerCase() + '. O atendimento ' + typeLabels[other].toLowerCase() + ' está disponível em outros dias.'
                : '');

        if ($('#type').val()) {
            renderTimes(selectedDay.slots[$('#type').val()] || []);
            return;
        }

        $('#horarios').html('');
        $('#horariosWrap').addClass('d-none');
    }

    $('#modalidadeWrap').on('click', '.modalidade-btn', function () {
        $('#modalidadeWrap .modalidade-btn').removeClass('active').attr('aria-pressed', 'false');
        $(this).addClass('active').attr('aria-pressed', 'true');
        $('#type').val($(this).data('type'));
        renderTimes(selectedDay.slots[$('#type').val()] || []);
    });

    function renderTimes(slots) {
        const container = $('#horarios').html('');
        const type = $('#type').val();
        $('#horariosWrap').removeClass('d-none');
        // A modalidade fica junto dos horários para não haver dúvida se são online ou presenciais
        $('#horariosModalidade').html('').append(
            $('<i></i>').addClass('bi ' + typeIcons[type]),
            document.createTextNode(typeLabels[type])
        );

        slots.forEach(function (time) {
            const btn = $('<button type="button" class="hora-btn"></button>')
                .text(time)
                .on('click', function () {
                    $('#horarios .hora-btn').removeClass('active');
                    $(this).addClass('active');
                    $('#time').val(time);
                    setTimeout(function () { goTo('name'); }, 250);
                });

            container.append(btn);
        });
    }

    function goTo(step) {
        const index = typeof step === 'number' ? step : steps.indexOf(step);

        if (index < 0) return;

        current = index;

        $(form).find('.step').removeClass('active').filter(function () {
            return $(this).data('step') === steps[current];
        }).addClass('active');

        $('#progresso span').each(function (i) {
            $(this).toggleClass('done', i <= current);
        });

        renderSummary();

        const isLast = current === steps.length - 1;
        const isChoiceStep = ['service', 'datetime'].includes(steps[current]);

        $('#btnVoltar').prop('hidden', current === 0);
        $('#btnProximo').toggleClass('d-none', isChoiceStep || isLast);
        $('#btnConfirmar').toggleClass('d-none', !isLast);

        $(form).find('.step.active input.form-control').first().trigger('focus');
    }

    function renderSummary() {
        const parts = [];

        if (labels.service && current > steps.indexOf('service')) parts.push(labels.service);
        if (selectedDay && $('#time').val() && current > steps.indexOf('datetime')) {
            const d = new Date(selectedDay.date + 'T00:00:00');
            parts.push(d.getDate() + ' de ' + months[d.getMonth()] + ' às ' + $('#time').val());

            if (typeLabels[$('#type').val()]) parts.push(typeLabels[$('#type').val()]);
        }

        $('#resumo').html('').append(parts.map(function (text) {
            return $('<strong></strong>').text(text);
        }).reduce(function (acc, el, i) {
            return acc.concat(i ? [' · ', el] : [el]);
        }, []));
    }

    function isCpfFilled(selector) {
        return validarCPF($(selector).val()).valido;
    }

    function validateField(selector, valid) {
        $(selector).toggleClass('is-invalid', !valid);

        if (!valid) $(selector).trigger('focus');

        return valid;
    }

    function validateDocumentStep() {
        if ($('#no_document').is(':checked')) return true;

        if (!validateField('#document', isCpfFilled('#document'))) return false;

        const answer = $('input[name="has_payer"]:checked').val();

        $('#has-payer-error').toggleClass('d-block', answer === undefined);

        if (answer === undefined) return false;

        if (answer === '0') return true;

        const sameDocument = $('#payer_document').val() === $('#document').val();

        return validateField('#payer_name', $('#payer_name').val().trim() !== '')
            && validateField('#payer_document', isCpfFilled('#payer_document') && !sameDocument);
    }

    function validateStep() {
        if (steps[current] === 'document') return validateDocumentStep();

        const field = $(form).find('.step.active input.form-control');

        if (!field.length) return true;

        const valid = field[0].checkValidity() && field.val().trim() !== '';
        field.toggleClass('is-invalid', !valid);

        if (!valid) field.trigger('focus');

        return valid;
    }

    function nextStep() {
        if (validateStep() && current < steps.length - 1) goTo(current + 1);
    }

    $(form).on('click', '.opt-btn', function () {
        const btn = $(this);
        const stepName = btn.closest('.step').data('step');

        btn.siblings().removeClass('active');
        btn.addClass('active');
        $(btn.data('target')).val(btn.data('value'));
        labels[stepName] = btn.data('label');

        setTimeout(function () { goTo(current + 1); }, 250);
    });

    $('#no_document').on('change', function () {
        $('#no-document-hint').toggleClass('d-none', !this.checked);
        $('#payer-question').toggleClass('d-none', this.checked);

        if (this.checked) {
            $('input[name="has_payer"]').prop('checked', false);
            togglePayerFields(false);
            $('#has-payer-error').removeClass('d-block');
        }
    });

    function togglePayerFields(show) {
        $('#payer-fields').toggleClass('d-none', !show);

        if (!show) $('#payer_name, #payer_document').val('').removeClass('is-invalid');
    }

    $('input[name="has_payer"]').on('change', function () {
        $('#has-payer-error').removeClass('d-block');
        togglePayerFields(this.value === '1');
    });

    $('#btnProximo').on('click', nextStep);

    $('#btnVoltar').on('click', function () {
        if (current > 0) goTo(current - 1);
    });

    $(form).find('input.form-control').on('input', function () {
        $(this).removeClass('is-invalid');
    }).on('keydown', function (e) {
        if (e.key === 'Enter' && current < steps.length - 1) {
            e.preventDefault();
            nextStep();
        }
    });

    function storePublicBooking() {
        if (!$('#service_id').val() || !$('#date').val() || !$('#time').val() || !$('#type').val()) {
            goTo(!$('#service_id').val() ? 'service' : 'datetime');
            return;
        }

        if (!validateStep()) return;

        sendRequest(
            '/lp/' + slug + '/book',
            'POST',
            form,
            'json',
            function (response) {
                window.location.href = response.link;
            },
            true,
            function (jqXHR) {
                const errors = jqXHR.responseJSON && jqXHR.responseJSON.errors;

                if (errors && (errors.date || errors.time || errors.service_id || errors.type)) {
                    clickWarningMessage('Quase lá', 'Escolha o serviço, o dia, a modalidade e o horário para continuar.');
                    goTo(errors.service_id ? 'service' : 'datetime');
                    return;
                }

                errorResponseJson(jqXHR, form);

                const fieldStep = ['name', 'phone', 'document'].find(function (field) {
                    return errors && errors[field];
                });

                if (fieldStep) {
                    goTo(fieldStep);
                    return;
                }

                if (errors && (errors.has_payer || errors.payer_name || errors.payer_document)) {
                    goTo('document');
                    return;
                }

                // O horário pode ter sido ocupado por outra pessoa: descarta-o e recarrega a disponibilidade
                rejected[$('#date').val() + ' ' + $('#time').val()] = true;
                loadAvailability(function () {
                    goTo('datetime');
                });
            }
        );
    }
});
