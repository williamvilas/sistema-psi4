function paginatePaymentOrders(callback = null) {
    const form = '#form-search';
    totalFilters(form);
    $('#filterModal').modal('hide');

    sendRequest(
        '/payments/paginate',
        'GET',
        form,
        'json',
        function(response) {
            $('.list-paginate').html(response.html);
            $('.payments-summary').html(response.summary);
            syncPaymentStatusFilter();
            if (isFunction(callback)) {
                callback();
            }
        }
    );
}

function filterPaymentOrdersByStatus(status) {
    $('#form-search [name="status"]').val(status || '');
    paginatePaymentOrders();
}

function syncPaymentStatusFilter() {
    const status = $('#form-search [name="status"]').val() || '';

    $('.dash-filter-chip, .pay-tile').removeClass('active');
    $('.dash-filter-chip').filter(function () {
        return $(this).attr('data-status') === status;
    }).addClass('active');

    if (status) {
        $('.pay-tile[data-status="' + status + '"]').addClass('active');
    }
}

function clearFiltersPaymentOrders() {
    clearForm();
    $('#form-search #patient_id').val(null).trigger('change');
    paginatePaymentOrders();
}

function storePaymentOrder() {
    sendRequest(
        '/payments',
        'POST',
        '#form-store-payment-order',
        'json',
        function (response) {
            return redirectTo(response.link);
        }
    );
}

function updatePaymentOrder() {
    sendRequest(
        '/payments/' + getId(),
        'PUT',
        '#form-update-payment-order',
        'json',
        function (response) {
            return redirectTo(response.link);
        }
    );
}

function initPaymentOrderForm(form) {
    if (!elementExists(form)) return;

    initPaymentPatientSelect(form);
    initOnlyDatePickers(updatePaymentOrderPreview, moment().add(1, 'days').startOf('day'));

    // maskMoney altera o valor no keypress sem disparar "input", por isso o keyup
    $(form).on('input change keyup', 'input, select', updatePaymentOrderPreview);
    $(form).on('change', '[name="due_preset"]', function () {
        selectDueDatePreset(form, this.value);
    });
    $(form).on('change', '[name="payment_methods[]"]', function () {
        keepOnePaymentMethod(form, this);
    });

    updatePaymentOrderPreview();
}

function initPaymentPatientSelect(form) {
    const select = form + ' select#patient_id';
    if (!elementExists(select)) return;

    $(select).select2({
        language: 'pt-BR',
        placeholder: 'Pesquisar paciente...',
        minimumInputLength: 2,
        ajax: {
            url: '/patients/autocomplete',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data.map(function (item) {
                        return {
                            id: item.id,
                            text: item.name + ' - ' + formatWhatsApp(item.phone),
                            name: item.name,
                            phone: item.phone
                        };
                    })
                };
            }
        },
        templateResult: function (item) {
            if (!item.name) return item.text;

            return $('<span>').text(item.name).append(
                $('<small class="pay-select-phone">').text(formatWhatsApp(item.phone))
            );
        }
    }).on('select2:select', function () {
        $(form + ' #amount').trigger('focus');
    });
}

function selectDueDatePreset(form, preset) {
    const $input = $(form + ' #due_date');
    const isCustom = preset === 'custom';

    $(form + ' .pay-due-custom').prop('hidden', !isCustom);
    $input.removeClass('is-invalid');
    $(form + ' .invalid-feedback-due_date').css('display', 'none').html('');

    if (isCustom) {
        const picker = $input.data('daterangepicker');
        if (picker) picker.show();
    } else {
        $input.val(preset === 'none' ? '' : moment().add(parseInt(preset, 10), 'days').format('DD/MM/YYYY'));
    }

    updatePaymentOrderPreview();
}

function keepOnePaymentMethod(form, checkbox) {
    const $feedback = $(form + ' .invalid-feedback-payment_methods');

    if ($(form + ' [name="payment_methods[]"]:checked').length > 0) {
        $feedback.css('display', 'none').html('');
        return;
    }

    $(checkbox).prop('checked', true);
    $feedback.css('display', 'block').text('Selecione ao menos uma forma de pagamento.');
    // O resumo já foi recalculado sem nenhuma forma pelo handler genérico do formulário
    updatePaymentOrderPreview();
}

function formatPaymentDueDate(value) {
    const due = moment(value, 'DD/MM/YYYY', true);
    if (!value || !due.isValid()) return 'Sem vencimento';

    const days = due.diff(moment().startOf('day'), 'days');
    let label = 'Vence em ' + days + ' dias';

    if (days < 0) label = 'Data no passado';
    else if (days === 0) label = 'Vence hoje';
    else if (days === 1) label = 'Vence amanhã';

    return label + ' · ' + due.format('DD/MM/YYYY');
}

function updatePaymentOrderPreview() {
    const $form = $('.pay-form');
    if ($form.length === 0) return;

    const select = $form.find('select#patient_id');
    if (select.length) {
        const data = select.select2('data')[0];
        const name = data ? (data.name || $(data.element).data('name') || data.text) : '';
        $form.find('[data-preview="patient"]').text(name || 'Selecione o paciente');
        $form.find('[data-preview="patient-wrap"]').toggleClass('is-empty', !name);
    }

    const amount = ($form.find('#amount').val() || '').trim();
    const hasAmount = /[1-9]/.test(amount);
    $form.find('[data-preview="amount"]').text(hasAmount ? amount : 'R$ 0,00').toggleClass('is-empty', !hasAmount);

    $form.find('[data-preview="due"]').text(formatPaymentDueDate($form.find('#due_date').val()));

    const methods = $form.find('[name="payment_methods[]"]:checked').map(function () {
        return $(this).next('.pay-method').find('strong').text();
    }).get();
    $form.find('[data-preview="methods"]').text(methods.length ? methods.join(' · ') : '—');

    const description = ($form.find('#description').val() || '').trim();
    $form.find('#description-count').text(($form.find('#description').val() || '').length);
    $form.find('[data-preview="description"]').text(description);
    $form.find('[data-preview="description-wrap"]').prop('hidden', !description);
}

/**
 * Enquanto o job gera o checkout no Mercado Pago, recarrega a tela de detalhes
 * a cada poucos segundos (sem endpoint novo). A tentativa atual fica na URL
 * para limitar as recargas.
 */
function watchPaymentLink(selector, maxAttempts = 8, interval = 4000) {
    if (!elementExists(selector)) return;

    const url = new URL(window.location.href);
    const attempt = parseInt(url.searchParams.get('tentativa') || '0', 10);

    if (attempt >= maxAttempts) {
        $(selector).find('[data-payment-generating-text]').text('Está demorando mais que o normal. Atualize a página em alguns instantes.');
        return;
    }

    setTimeout(function () {
        url.searchParams.set('tentativa', attempt + 1);
        window.location.replace(url.toString());
    }, interval);
}

function copyPaymentLink(link) {
    copyToClipboard(link, 'Link copiado');
}

function deletePaymentOrders(id, name, redirect = false) {
    confirmAction('Tem certeza que deseja <strong>apagar</strong> a ordem de pagamento do paciente <strong>' + $('<div>').text(name).html() + '</strong>?', function () {
        sendRequestByJson(
            '/payments',
            'DELETE',
            { ids: [id] },
            function(response) {

                if (redirect) {
                    return redirectTo(response.link);
                }

                paginatePaymentOrders(function() {
                    successResponseJson(response.title, response.message);
                });
            }
        );
    }, 'danger', 'Apagar', 'Cancelar');
}

function refundPaymentOrder(id, name, reload = false) {
    confirmAction('Tem certeza que deseja <strong>estornar</strong> o pagamento do paciente <strong>' + $('<div>').text(name).html() + '</strong>? O valor será devolvido ao pagador pelo Mercado Pago.', function () {
        sendRequestByJson(
            '/payments/' + id + '/refund',
            'POST',
            {},
            function(response) {

                if (reload) {
                    return window.location.reload();
                }

                paginatePaymentOrders(function() {
                    successResponseJson(response.title, response.message);
                });
            }
        );
    }, 'danger', 'Estornar', 'Cancelar');
}
