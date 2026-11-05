function initHealthReceiptsPage() {
    if (!elementExists('#form-store-health-receipt')) return;

    initOnlyDatePickers();
    initHealthReceiptPatientSelect();

    $('#form-store-health-receipt').on('submit', function (e) {
        e.preventDefault();
        const id = $('#health_receipt_id').val();
        return id ? updateHealthReceipt(id) : storeHealthReceipt();
    });

    $('#form-store-health-receipt-batch').on('submit', function (e) {
        e.preventDefault();
        storeHealthReceiptBatch();
    });

    // A paginação global substitui o .list-paginate; a dos arquivos tem o próprio container
    $('.list-paginate-batches').on('click', '.pagination .page-link', function (e) {
        e.preventDefault();
        e.stopPropagation();
        paginateHealthReceiptBatches($(this).attr('href'));
    });
}

function paginateHealthReceipts(callback = null) {
    const form = '#form-search';
    totalFilters(form);
    $('#filterModal').modal('hide');

    sendRequest(
        '/health-receipts/paginate',
        'GET',
        form,
        'json',
        function (response) {
            $('.list-paginate').html(response.html);
            $('.health-receipts-overview').html(response.overview);
            syncHealthReceiptStatusFilter();
            if (isFunction(callback)) {
                callback();
            }
        }
    );
}

function paginateHealthReceiptBatches(url = '/health-receipts/batches/paginate', callback = null) {
    $.ajax({
        url: url,
        type: 'GET',
        dataType: 'json',
        beforeSend: function () {
            loadingAjax();
        },
        success: function (response) {
            closeLoadingAjax();
            $('.list-paginate-batches').html(response.html);
            if (isFunction(callback)) {
                callback();
            }
        },
        error: function (jqXHR) {
            closeLoadingAjax();
            errorResponseJson(jqXHR);
        }
    });
}

function filterHealthReceiptsByStatus(status) {
    $('#form-search [name="status"]').val(status || '');
    paginateHealthReceipts();
}

function syncHealthReceiptStatusFilter() {
    const status = $('#form-search [name="status"]').val() || '';

    $('.dash-filter-chip').removeClass('active').filter(function () {
        return $(this).attr('data-status') === status;
    }).addClass('active');
}

function clearFiltersHealthReceipts() {
    clearForm();
    $('#form-search #patient_id').val(null).trigger('change');
    paginateHealthReceipts();
}

function initHealthReceiptPatientSelect() {
    const select = '#health_receipt_patient_id';
    if (!elementExists(select)) return;

    $(select).select2({
        language: 'pt-BR',
        placeholder: 'Pesquisar paciente...',
        minimumInputLength: 2,
        dropdownParent: $('#healthReceiptModal'),
        ajax: {
            url: '/patients/autocomplete',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term };
            },
            processResults: function (data) {
                return {
                    results: data.map(function (item) {
                        return {
                            id: item.id,
                            text: item.name + ' - ' + formatWhatsApp(item.phone)
                        };
                    })
                };
            }
        }
    });
}

function resetHealthReceiptForm() {
    const form = '#form-store-health-receipt';
    clearFormError(form);
    $(form)[0].reset();
    $('#health_receipt_id').val('');
    $('#health_receipt_patient_id').empty().val(null).trigger('change');
}

function openStoreHealthReceipt() {
    resetHealthReceiptForm();
    $('#healthReceiptModalLabel').text('Registrar recebimento');
    $('#health_receipt_paid_on').val(moment().format('DD/MM/YYYY'));
    $('#healthReceiptModal').modal('show');
}

function openUpdateHealthReceipt(button) {
    const data = $(button).data();

    resetHealthReceiptForm();
    $('#healthReceiptModalLabel').text('Editar recebimento');
    $('#health_receipt_id').val(data.id);
    $('#health_receipt_patient_id').append(new Option(data.patientName, data.patientId, true, true)).trigger('change');
    $('#health_receipt_amount').val(data.amount);
    $('#health_receipt_paid_on').val(data.paidOn);
    $('#health_receipt_description').val(data.description || '');
    $('#healthReceiptModal').modal('show');
}

function storeHealthReceipt() {
    sendRequest(
        '/health-receipts',
        'POST',
        '#form-store-health-receipt',
        'json',
        function (response) {
            $('#healthReceiptModal').modal('hide');
            paginateHealthReceipts(function () {
                successResponseJson(response.title, response.message);
            });
        }
    );
}

function updateHealthReceipt(id) {
    sendRequest(
        '/health-receipts/' + id,
        'PUT',
        '#form-store-health-receipt',
        'json',
        function (response) {
            $('#healthReceiptModal').modal('hide');
            paginateHealthReceipts(function () {
                successResponseJson(response.title, response.message);
            });
        }
    );
}

function deleteHealthReceipt(id, name) {
    confirmAction('Tem certeza que deseja <strong>apagar</strong> o recebimento de <strong>' + $('<div>').text(name).html() + '</strong>?', function () {
        sendRequestByJson(
            '/health-receipts/' + id,
            'DELETE',
            {},
            function (response) {
                paginateHealthReceipts(function () {
                    successResponseJson(response.title, response.message);
                });
            }
        );
    }, 'danger', 'Apagar', 'Cancelar');
}

function skipHealthReceipt(id, name) {
    confirmAction(
        'O recibo de <strong>' + $('<div>').text(name).html() + '</strong> não vai para o arquivo do Carnê-Leão.<br><br>' +
        '<small>A emissão do Receita Saúde é obrigatória para todo pagamento recebido de pessoa física. Use esta opção só se o pagamento não aconteceu ou já foi declarado de outra forma. Na dúvida, fale com seu contador.</small>',
        function () {
            sendRequestByJson(
                '/health-receipts/' + id + '/skip',
                'POST',
                {},
                function (response) {
                    paginateHealthReceipts(function () {
                        successResponseJson(response.title, response.message);
                    });
                }
            );
        }, 'warning', 'Não emitir', 'Cancelar'
    );
}

function restoreHealthReceipt(id) {
    sendRequestByJson(
        '/health-receipts/' + id + '/restore',
        'POST',
        {},
        function (response) {
            paginateHealthReceipts(function () {
                successResponseJson(response.title, response.message);
            });
        }
    );
}

function openGenerateHealthReceiptBatch() {
    const form = '#form-store-health-receipt-batch';
    clearFormError(form);

    // Sugere o mês anterior, que é a rotina mais comum de escrituração
    $('#batch_start_date').val(moment().subtract(1, 'month').startOf('month').format('DD/MM/YYYY'));
    $('#batch_end_date').val(moment().subtract(1, 'month').endOf('month').format('DD/MM/YYYY'));
    $('#generateBatchModal').modal('show');
}

function storeHealthReceiptBatch() {
    sendRequest(
        '/health-receipts/batches',
        'POST',
        '#form-store-health-receipt-batch',
        'json',
        function (response) {
            $('#generateBatchModal').modal('hide');

            const downloads = response.downloads || [];
            const skipped = response.skipped || 0;
            let html = '';

            if (downloads.length === 1) {
                window.location.href = downloads[0].url;
                html = 'O download do arquivo com <strong>' + downloads[0].count + '</strong> recibo(s) começou.';
            } else {
                html = 'Foram gerados ' + downloads.length + ' arquivos (o Carnê-Leão aceita até 1.000 linhas por arquivo). Importe um de cada vez:<br>' +
                    downloads.map(function (item, index) {
                        return '<a href="' + item.url + '">Arquivo ' + (index + 1) + ' (' + item.count + ' recibos)</a>';
                    }).join('<br>');
            }

            if (skipped > 0) {
                html += '<br><br><small>' + skipped + ' recibo(s) com pendência ficaram de fora. Corrija e gere um novo arquivo.</small>';
            }

            html += '<br><br><small>Depois de importar no e-CAC, marque o arquivo como importado em "Arquivos gerados".</small>';

            Swal.fire({ icon: 'success', title: 'Arquivo gerado', html: html });

            paginateHealthReceipts();
        }
    );
}

function downloadHealthReceiptBatch(url, imported) {
    if (!imported) {
        window.location.href = url;
        return;
    }

    confirmAction(
        'Este arquivo já foi importado no e-CAC. <strong>Importar de novo duplica os recibos na Receita.</strong> Deseja baixar mesmo assim?',
        function () {
            window.location.href = url;
        }, 'warning', 'Baixar', 'Cancelar'
    );
}

function markHealthReceiptBatchImported(id) {
    sendRequestByJson(
        '/health-receipts/batches/' + id + '/imported',
        'POST',
        {},
        function (response) {
            paginateHealthReceiptBatches(undefined, function () {
                successResponseJson(response.title, response.message);
            });
        }
    );
}

function undoHealthReceiptBatch(id, imported) {
    const message = imported
        ? 'Use esta opção depois de <strong>desfazer a escrituração no e-CAC</strong> (Histórico da Escrituração → Desfazer). Os recibos voltam para a lista de emissão do PSI4.'
        : 'Os recibos deste arquivo voltam para a lista de emissão. Se você já importou o arquivo no e-CAC, desfaça a escrituração lá também.';

    confirmAction(message, function () {
        sendRequestByJson(
            '/health-receipts/batches/' + id + '/undo',
            'POST',
            {},
            function (response) {
                paginateHealthReceiptBatches(undefined, function () {
                    successResponseJson(response.title, response.message);
                });
                paginateHealthReceipts();
            }
        );
    }, 'danger', 'Desfazer', 'Cancelar');
}
