let paginateAccessLogsSeq = 0;

function paginateAccessLogs(callback = null) {
    if (!elementExists('#form-search') || !elementExists('[name="action"]')) return;

    const seq = ++paginateAccessLogsSeq;

    sendRequest(
        '/access-logs/paginate',
        'GET',
        '#form-search',
        'json',
        function(response) {
            // ignora respostas de buscas antigas que chegaram depois da mais recente
            if (seq !== paginateAccessLogsSeq) {
                return;
            }
            $('.list-paginate').html(response.html);
            if (isFunction(callback)) {
                callback();
            }
        }
    );
}

function clearFiltersAccessLogs() {
    $('#form-search #action').val('');
    $('#form-search #search_date').val('');
    paginateAccessLogs();
}

// Painel lateral "Acessos" das telas do prontuário (visualizar e editar).
let paginateAccessLogsPanelSeq = 0;

function initAccessLogsPanel() {
    if (!elementExists('#docAccessPanel')) return;

    initDocSidePanels();

    $('#docAccessToggle').on('click', () => toggleAccessLogsPanel());
    $('#docAccessClose').on('click', () => toggleAccessLogsPanel(false));
    $('#docAccessRefresh').on('click', () => paginateAccessLogsPanel());
    $('#docAccessAction').on('change', () => paginateAccessLogsPanel());
    $('#docAccessClear').on('click', clearFiltersAccessLogsPanel);
    $('#form-search-access-logs-panel').on('submit', function (e) {
        e.preventDefault();
        paginateAccessLogsPanel();
    });

    $('#docAccessList').on('click', '.js-page-link', function (e) {
        e.preventDefault();
        const url = $(this).attr('href');
        if (url) paginateAccessLogsPanel(url);
    });

    initDateRangePickers(() => paginateAccessLogsPanel(), true);
}

function toggleAccessLogsPanel(open) {
    const show = toggleDocSidePanel('docAccessPanel', open);

    // carrega só na primeira abertura; depois, pelo botão Atualizar ou filtros
    if (show && !$('#docAccessList').data('loaded')) {
        paginateAccessLogsPanel();
    }
}

// url: link de paginação (já traz os filtros); sem url, usa o formulário de filtros do painel.
function paginateAccessLogsPanel(url = null) {
    if (!elementExists('#docAccessList')) return;

    const seq = ++paginateAccessLogsPanelSeq;
    const list = $('#docAccessList');

    $.ajax({
        url: url || '/access-logs/paginate',
        type: 'GET',
        dataType: 'json',
        data: url ? null : $('#form-search-access-logs-panel').serialize(),
        beforeSend: function () {
            list.attr('aria-busy', 'true').html('<div class="doc-access-loading"><i class="fas fa-spinner fa-spin me-2"></i> Carregando...</div>');
        },
        success: function (response) {
            // ignora respostas de buscas antigas que chegaram depois da mais recente
            if (seq !== paginateAccessLogsPanelSeq) return;

            list.data('loaded', true).html(response.html).scrollTop(0);
        },
        error: function (jqXHR) {
            if (seq !== paginateAccessLogsPanelSeq) return;

            list.html('<div class="doc-access-empty"><p class="mb-3">Não foi possível carregar os acessos.</p>' +
                '<button type="button" class="btn btn-outline-primary btn-sm" onclick="paginateAccessLogsPanel()">Tentar novamente</button></div>');
            errorResponseJson(jqXHR);
        },
        complete: function () {
            if (seq === paginateAccessLogsPanelSeq) list.removeAttr('aria-busy');
        }
    });
}

function clearFiltersAccessLogsPanel() {
    $('#docAccessAction').val('');
    $('#docAccessDate').val('');
    paginateAccessLogsPanel();
}
