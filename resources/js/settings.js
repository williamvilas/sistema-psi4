$(document).ready(function() {
    $('#btn-import-holidays').on('click', function () {
        importFeriadosNacionais('/settings/national-holidays');
    });
    storeSettings('#form-store-agenda', '/settings/agenda', validateAgendaHours, function () {
        $('#container-feriados .feriado-item').removeClass('is-new');
    });
    storeSettings('#form-store-psychologist', '/settings/psychologist', function () {
        if (typeof prepareCroppedPhoto === 'function') {
            prepareCroppedPhoto();
        }
    }, function () {
        if (typeof resetPhotoCropper === 'function') {
            resetPhotoCropper();
        }
    });
    storeSettings('#form-store-booking', '/settings/booking', null, updatePublicBookingLink);
    bindSlugAvailability('#slug', '#btn-check-slug', '#slug-availability', '/settings/booking/slug-availability');
    bindPublicBookingToggle('#publicBookingEnabled', '/settings/booking/toggle');
    bindZipcodeLookup('#form-store-psychologist', {
        zipcode: '#cep',
        city: '#cidade',
        neighborhood: '#bairro',
        street: '#rua',
        complement: '#complemento',
        number: '#numero',
        button: '#btn-search-zipcode',
    });
    bindCnpjLookup('#form-store-psychologist', {
        cnpj: '#cnpj',
        clinicName: '#nomeClinica',
        zipcode: '#cep',
        city: '#cidade',
        neighborhood: '#bairro',
        street: '#rua',
        complement: '#complemento',
        number: '#numero',
        button: '#btn-search-cnpj',
    });
    initSettingsPage();
});

/**
 * Inicializa a tela de Configurações: menu de seções, horários, foto e
 * controle de alterações não salvas.
 */
function initSettingsPage() {
    const page = document.getElementById('settings-page');
    if (!page) return;

    setupPhotoCropper(page.dataset.photoUrl || null);
    initOnlyDatePickers();
    bindScheduleSlots('#form-store-agenda');
    bindDirtyForms(['#form-store-agenda', '#form-store-booking', '#form-update-google-calendar', '#form-store-psychologist']);
    bindSettingsTabs();
    bindSlugInput();
    bindBookingLinkCopy();
    handleMercadoPagoConnectResult();
    handleGoogleCalendarConnectResult(page.dataset.scopeHelpGif);
    loadGoogleCalendars();
    bindGoogleCalendarForm();

    $(document).on('focus', '.money-service', function () {
        $(this).mask('#.##0,00', {reverse: true});
    });
}

/**
 * Menu lateral: #<id-da-seção> abre a seção e sobrevive ao F5.
 * O hash antigo #agenda continua abrindo Dias de atendimento.
 */
function bindSettingsTabs() {
    restoreSettingsTabFromHash();
    window.addEventListener('hashchange', restoreSettingsTabFromHash);

    document.querySelectorAll('#settingsNav [data-bs-toggle="tab"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function (event) {
            const id = event.target.id.replace(/-tab$/, '').replace(/-parent$/, '');
            history.replaceState(null, '', window.location.pathname + window.location.search + '#' + id);
        });
    });
}

function restoreSettingsTabFromHash() {
    let hash = window.location.hash.replace('#', '');
    if (hash === 'agenda') hash = 'horarios';
    if (!hash) return;

    const tab = document.getElementById(hash + '-tab') || document.getElementById(hash + '-parent-tab');
    if (tab && tab.closest('#settingsNav')) {
        showSettingsTab(tab.id);
    }
}

function showSettingsTab(tabId) {
    const tab = document.getElementById(tabId);
    if (!tab || typeof bootstrap === 'undefined') return;

    bootstrap.Tab.getOrCreateInstance(tab).show();
}

/**
 * Guarda um retrato de cada formulário e marca como "não salvo" quando o
 * conteúdo muda (barra de ações + ponto no menu); avisa antes de sair da página.
 */
function bindDirtyForms(selectors) {
    selectors.forEach(function (selector) {
        const form = document.querySelector(selector);
        if (!form) return;

        resetFormDirty(form);

        $(form).on('input change', function (event) {
            if (event.target.matches('[data-dirty-ignore]')) return;

            // Foto nova/zoom não aparecem na serialização: força o estado alterado.
            if (event.target.type === 'file' || event.target.id === 'photoZoom') {
                form.dataset.forceDirty = '1';
            }

            refreshFormDirty(form);
        });
    });

    $('#photoCanvasWrap').on('pointerup', function () {
        const form = document.getElementById('form-store-psychologist');
        if (!form || !$('#photoZoom').is(':enabled')) return;

        form.dataset.forceDirty = '1';
        refreshFormDirty(form);
    });

    window.addEventListener('beforeunload', function (event) {
        if (!document.querySelector('form.is-dirty')) return;

        event.preventDefault();
        event.returnValue = '';
    });
}

function serializeForDirty(form) {
    return $(form).find(':input').not('[data-dirty-ignore]').serialize();
}

function resetFormDirty(form) {
    if (!form) return;

    form.dataset.snapshot = serializeForDirty(form);
    delete form.dataset.forceDirty;
    refreshFormDirty(form);
}

function refreshFormDirty(form) {
    if (!form || form.dataset.snapshot === undefined) return;

    const dirty = form.dataset.forceDirty === '1' || serializeForDirty(form) !== form.dataset.snapshot;

    form.classList.toggle('is-dirty', dirty);
    $(form).find('.cfg-actions').toggleClass('is-dirty', dirty);
    $('#settingsNav [data-form="#' + form.id + '"]').toggleClass('is-dirty', dirty);
}

/**
 * Antes de salvar Agenda/Feriados, confere se o fim de cada horário ativo é
 * depois do início; se não for, abre a seção com o problema e cancela o envio.
 */
function validateAgendaHours() {
    const invalid = findInvalidScheduleRanges($('#form-store-agenda'));

    if (!invalid.length) return true;

    showSettingsTab(invalid[0].closest('#feriados').length ? 'feriados-tab' : 'horarios-tab');
    invalid[0].eq(1).trigger('focus');
    clickWarningMessage('Confira os horários', 'O horário de término precisa ser depois do horário de início.');

    return false;
}

/**
 * Ao terminar de digitar o CNPJ (14 dígitos) busca a empresa em /cnpj/{cnpj} e
 * preenche nome da clínica (nome fantasia ou, na falta dele, razão social) e
 * os campos de endereço. Os campos são limpos antes de cada busca e ao apagar
 * o CNPJ. O botão (lupa) permite fazer a mesma busca manualmente.
 */
function bindCnpjLookup(selectorForm, fields) {
    if (!elementExists(selectorForm) || !elementExists(fields.cnpj)) return;

    const $form = $(selectorForm);
    let lastCnpj = '';

    $form.find(fields.cnpj).on('input', function () {
        const cnpj = $(this).val().replace(/\D/g, '');

        if (cnpj.length === 0) {
            lastCnpj = '';
            clearCompany();
            return;
        }

        if (cnpj.length < 14) {
            lastCnpj = '';
            return;
        }

        if (cnpj === lastCnpj) return;
        lastCnpj = cnpj;

        fillCompany(cnpj);
    });

    if (fields.button && elementExists(fields.button)) {
        $form.find(fields.button).on('click', function () {
            const cnpj = $form.find(fields.cnpj).val().replace(/\D/g, '');

            if (cnpj.length !== 14) {
                clickWarningMessage('CNPJ incompleto', 'Informe um CNPJ válido, com 14 dígitos.');
                return;
            }

            lastCnpj = cnpj;
            fillCompany(cnpj);
        });
    }

    function clearCompany() {
        $form.find([fields.clinicName, fields.zipcode, fields.city, fields.neighborhood, fields.street, fields.number, fields.complement].join(',')).val('');
    }

    function fillCompany(cnpj) {
        // Limpa os campos preenchidos antes de buscar, para não misturar dados de empresas diferentes
        clearCompany();

        // Troca a lupa por um loading enquanto a busca está em andamento
        const $icon = fields.button ? $form.find(fields.button).find('i') : $();
        $icon.removeClass('fa-search').addClass('fa-spinner fa-spin');

        searchCnpj(cnpj, function (company) {
            // Ignora resposta atrasada se o CNPJ mudou durante a requisição
            if ($form.find(fields.cnpj).val().replace(/\D/g, '') !== cnpj) return;

            const clinicName = company.tradeName || company.legalName;

            if (clinicName) {
                $form.find(fields.clinicName).val(clinicName);
            }

            $form.find(fields.zipcode).val(applyMask('#####-###', company.zip || ''));
            $form.find(fields.city).val(company.city || '');
            $form.find(fields.neighborhood).val(company.neighborhood || '');
            $form.find(fields.street).val(company.address || '');
            // O campo número é numérico: "SN" (sem número) resulta em campo vazio
            $form.find(fields.number).val(company.number || '');
            $form.find(fields.complement).val(company.complement || '');
        }, function () {
            $icon.removeClass('fa-spinner fa-spin').addClass('fa-search');
        });
    }
}

function searchCnpj(cnpj, callbackSuccess, callbackComplete = null) {
    $.ajax({
        url: '/cnpj/' + cnpj,
        type: 'GET',
        dataType: 'json',
        success: callbackSuccess,
        complete: callbackComplete,
        error: function (jqXHR) {
            if (isSessionExpired(jqXHR)) return;

            const message = jqXHR.responseJSON && jqXHR.responseJSON.message
                ? jqXHR.responseJSON.message
                : 'Não foi possível buscar o CNPJ. Tente novamente.';

            clickWarningMessage('CNPJ não encontrado', message);
        },
    });
}

function storeSettings(selectorForm, url, beforeSubmit = null, afterSuccess = null) {
    if (!elementExists(selectorForm)) return;

    $(selectorForm).submit(function (e) {
        e.preventDefault();

        // beforeSubmit pode cancelar o envio retornando false
        if (isFunction(beforeSubmit) && beforeSubmit() === false) {
            return;
        }

        sendRequest(
            url,
            'POST',
            selectorForm,
            'json',
            function (response) {
                successResponseJson(response.title, response.message);

                if (response.photo) {
                    $('.img-user-updated').attr('src', response.photo);
                }

                if (isFunction(afterSuccess)) {
                    afterSuccess();
                }

                resetFormDirty(document.querySelector(selectorForm));
            }
        );
    });
}

function paginateSettings(callback = null) {
    const form = '#form-search';
    totalFilters(form);
    $('#filterModal').modal('hide');

    sendRequest(
        '/settings/paginate',
        'GET',
        form,
        'json',
        function(response) {
            $('.list-paginate').html(response.html);
            if (isFunction(callback)) {
                callback();
            }
        }
    );
}

/**
 * Atualiza o link "Abrir minha página" com o slug digitado (o link nunca é ocultado).
 */
function updatePublicBookingLink() {
    if (!elementExists('#public-booking-link')) return;

    const slug = $('#slug').val().trim();

    $('#public-booking-url').attr('href', $('#slug').closest('.input-group').find('.input-group-text').text().trim() + slug);
}

/**
 * Ao alternar o switch da página pública de agendamento, envia imediatamente
 * o novo estado para o endpoint; em caso de erro, reverte o switch.
 */
function bindPublicBookingToggle(selector, url) {
    if (!elementExists(selector)) return;

    $(selector).on('change', function () {
        const $switch = $(this);
        const enabled = $switch.is(':checked');

        sendRequestByJson(
            url,
            'POST',
            { public_booking_enabled: enabled ? 1 : 0 },
            function (response) {
                updatePublicBookingLink();
                setPublicBookingStatus(enabled);
                successResponseJson(response.title, response.message);
            },
            function (jqXHR) {
                $switch.prop('checked', !enabled);
                errorResponseJson(jqXHR, '');
            }
        );
    });
}

function setPublicBookingStatus(enabled) {
    $('#public-booking-status')
        .toggleClass('dash-chip-success', enabled)
        .toggleClass('dash-chip-muted', !enabled)
        .text(enabled ? 'Ativa' : 'Inativa');
    $('#nav-status-booking').toggleClass('is-on', enabled).attr('title', enabled ? 'Página ativa' : 'Página inativa');
}

function bindSlugInput() {
    $('#slug').on('input', function () {
        this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-{2,}/g, '-');
        $('#slug-availability').addClass('d-none').empty();
    });
}

function bindBookingLinkCopy() {
    $('#btn-copy-booking-link').on('click', function () {
        if (!$('#slug').val().trim()) {
            clickWarningMessage('Falta o endereço da página', 'Informe e salve o endereço da página antes de copiar o link.');
            return;
        }

        updatePublicBookingLink();
        copyToClipboard($('#public-booking-url').attr('href'), 'Link copiado');
    });
}

function handleMercadoPagoConnectResult() {
    const params = new URLSearchParams(window.location.search);
    const mp = params.get('mp');
    if (!mp) return;

    showSettingsTab('pagamentos-parent-tab');

    const messages = {
        connected: ['Conta conectada', 'Sua conta do Mercado Pago foi conectada com sucesso.', 'success'],
        disconnected: ['Conta desconectada', 'Sua conta do Mercado Pago foi desconectada.', 'success'],
        denied: ['Conexão cancelada', 'Você cancelou a autorização com o Mercado Pago.', 'warning'],
        error: ['Não foi possível conectar', params.get('mp_message') || 'Tente novamente em instantes.', 'error'],
    };

    const [title, message, icon] = messages[mp] || [];
    if (title) {
        appDialog({ tone: icon, title: title, text: message });
    }

    clearQueryParams('mp', 'mp_message');
}

function handleGoogleCalendarConnectResult(scopeHelpGif) {
    const params = new URLSearchParams(window.location.search);
    const gc = params.get('gc');
    if (!gc) return;

    showSettingsTab('google-calendar-tab');
    clearQueryParams('gc', 'gc_message');

    if (gc === 'scope_missing') {
        appDialog({
            tone: 'warning',
            title: 'Faltou autorizar o acesso à agenda',
            html: 'O Google deixa as permissões <strong>desmarcadas</strong> por padrão. ' +
                'Na próxima tentativa, marque <strong>todas as opções</strong> (ou "Selecionar tudo") e clique em <strong>Continuar</strong>.',
            content: `<img src="${scopeHelpGif}"
                     alt="Como autorizar: marque todas as opções e clique em Continuar"
                     class="img-fluid rounded border">`,
            wide: true,
            confirmText: 'Tentar novamente',
            cancelText: 'Fechar',
            focusConfirm: false,
        }).then(function (result) {
            if (result.isConfirmed) {
                window.location.href = '/settings/google-calendar/connect';
            }
        });
        return;
    }

    const messages = {
        connected: ['Conta conectada', 'Sua conta do Google foi conectada com sucesso.', 'success'],
        disconnected: ['Conta desconectada', 'Sua conta do Google foi desconectada.', 'success'],
        denied: ['Conexão cancelada', 'Você cancelou a autorização com o Google.', 'warning'],
        error: ['Não foi possível conectar', params.get('gc_message') || 'Tente novamente em instantes.', 'error'],
    };

    const [title, message, icon] = messages[gc] || [];
    if (title) {
        appDialog({ tone: icon, title: title, text: message });
    }
}

function loadGoogleCalendars() {
    const select = document.getElementById('googleCalendarId');
    if (!select) return;

    const current = select.dataset.current;

    $.ajax({
        url: '/settings/google-calendar/calendars',
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            select.innerHTML = '';

            (response.calendars || []).forEach(function (calendar) {
                const option = new Option(calendar.summary + (calendar.primary ? ' (principal)' : ''), calendar.id);
                option.selected = calendar.id === current || (calendar.primary && current === 'primary');
                select.add(option);
            });

            // A troca de "Carregando..." pela lista não é uma alteração do usuário.
            resetFormDirty(document.getElementById('form-update-google-calendar'));
        },
        error: function (jqXHR) {
            errorResponseJson(jqXHR, '#form-update-google-calendar');
        },
    });
}

function bindGoogleCalendarForm() {
    const form = document.getElementById('form-update-google-calendar');
    if (!form) return;

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        sendRequestByJson(
            '/settings/google-calendar',
            'PUT',
            {
                calendar_id: document.getElementById('googleCalendarId').value,
                invite_patient: document.getElementById('googleInvitePatient').checked ? 1 : 0,
                block_busy: document.getElementById('googleBlockBusy').checked ? 1 : 0,
            },
            function () {
                resetFormDirty(form);
                successResponseJson('Tudo certo', 'Preferências do Google Agenda atualizadas.');
            }
        );
    });
}

function disconnectGoogleCalendar() {
    confirmAction('Tem certeza que deseja <strong>desconectar</strong> o Google Agenda? Seus pacientes não vão mais conseguir fazer novos agendamentos.', function () {
        sendRequestByJson(
            '/settings/google-calendar',
            'DELETE',
            {},
            function () {
                window.location.href = '/settings?gc=disconnected';
            }
        );
    });
}

function disconnectPaymentAccount() {
    confirmAction('Tem certeza que deseja <strong>desconectar</strong> sua conta do Mercado Pago? Novas cobranças deixarão de ser criadas até reconectar.', function () {
        sendRequestByJson(
            '/settings/payment-account',
            'DELETE',
            {},
            function () {
                window.location.href = '/settings?mp=disconnected';
            }
        );
    });
}
