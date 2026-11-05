let timeoutClassUpdeted = 3000;

$(document).ready(function(){
    $(document).on('keyup', 'input, textarea', function() {
        let input = $(this).attr('name');
        if (elementExists('.invalid-feedback-'+input)) {
            $('.invalid-feedback-'+input).css('display', 'none').html('');
            $('.invalid-feedback-'+input).not('.not-remove').remove();
            $(this).removeClass('is-invalid');
        }
    });

    $(document).on('focus', '.zipcode', function() {
        $(this).mask('00000-000');
    });

    $(document).on('focus', '.whatsapp', function() {
        $(this).mask('(00) 00000-0000');
    });

    $('input').attr('autocomplete', 'disabled');

    $('.time').mask('00:00:00');
    $('.date_time').mask('00/00/0000 00:00:00');
    $('.phone_with_ddd').mask('(00) 0000-0000');
    maskWhatsApp();
    maksCPF();

    // Sem CPF, o campo fica vazio e desabilitado: não é enviado
    $(document).on('change', '[data-no-document]', function () {
        const $document = $('#document');

        $document.prop('disabled', this.checked).removeClass('is-invalid');

        if (this.checked) {
            $document.val('');
        }
    });
    maskDate();

    $('.zipcode').mask('00000-000');
    $('.zipcode').attr('placeholder', '_____-___');

    $('.cnpj').attr('placeholder', '__.___.___/____-__');
    $('.cnpj').mask('00.000.000/0000-00', {reverse: true});

    $('.crp').attr('placeholder', '__/______');
    $('.crp').mask('00/000000');
    $('.money2').maskMoney({
        prefix: 'R$ ',
        allowNegative: true,
        thousands: '.',
        decimal: ',',
        affixesStay: true
    });
    $('.percent').mask('##0,00%', {reverse: true});
    $('.clear-if-not-match').mask("00/00/0000", {clearIfNotMatch: true});
});

$(document).on("change", "select", function() {
    let input = $(this).attr('name');
    input = strReplace(input, '[', '');
    input = strReplace(input, ']', '');

    if (elementExists('.invalid-feedback-'+input)) {
        $('.invalid-feedback-' + input).css('display', 'none').html('');
        $('.invalid-feedback-' + input).not('.not-remove').remove();
        $(this).removeClass('is-invalid');
    }
});

$(document).on('click', '.pagination .page-link:not(.js-page-link)', function (e) {
    e.preventDefault();
    let url = $(this).attr('href');

    if (url) {
        $.ajax({
            url: url,
            type: 'GET',
            beforeSend: function () {
                loadingAjax();
                $('.list-paginate').html('<p>Carregando...</p>');
            },
            success: function (data) {
                $('.list-paginate').html(data.html);
                closeLoadingAjax();
            },
            error: function (jqXHR) {
                closeLoadingAjax();
                errorResponseJson(jqXHR)
            }
        });
    }
});

function maskWhatsApp() {
    $('.whatsapp').mask('(00) 00000-0000', {placeholder: "(__) _____-____"});
}

function maksCPF() {
    $('.cpf').mask('000.000.000-00', {reverse: true, placeholder: "___.___.___-__"});
}

function maskDate() {
    $('.date').mask("00/00/0000", {placeholder: "__/__/____"});
}

function removePreview() {
    $('#preview-container').css('display', 'none');
    $('#preview').attr('src', '');
    $('#image').val('');
    $('.link-remove-preview').addClass('d-none');
}

function getTimezone() {
    let timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

    if (!timezone) {
        timezone = "America/Sao_Paulo";
    }

    return timezone;
}

function trunceDecimal(num) {
    return Math.trunc(num * 100) / 100;
}

function validarCPF(cpf) {
    // Remove caracteres não numéricos
    cpf = cpf.replace(/[^\d]/g, '');

    // Verifica se tem 11 dígitos ou se é uma sequência de dígitos repetidos
    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) {
        return {
            valido: false,
            mensagem: 'CPF deve conter 11 dígitos numéricos'
        };
    }

    // Validação do primeiro dígito verificador
    let soma = 0;
    for (let i = 0; i < 9; i++) {
        soma += parseInt(cpf.charAt(i)) * (10 - i);
    }
    let resto = (soma * 10) % 11;
    if (resto === 10 || resto === 11) resto = 0;
    if (resto !== parseInt(cpf.charAt(9))) {
        return {
            valido: false,
            mensagem: 'CPF inválido (dígito verificador incorreto)'
        };
    }

    // Validação do segundo dígito verificador
    soma = 0;
    for (let i = 0; i < 10; i++) {
        soma += parseInt(cpf.charAt(i)) * (11 - i);
    }
    resto = (soma * 10) % 11;
    if (resto === 10 || resto === 11) resto = 0;
    if (resto !== parseInt(cpf.charAt(10))) {
        return {
            valido: false,
            mensagem: 'CPF inválido (dígito verificador incorreto)'
        };
    }

    return {
        valido: true,
        mensagem: 'CPF válido',
    };
}

function totalFilters(form) {
    let totalFields = countFields(form);

    if (totalFields > 0) {
        $('.span-filter').removeClass('d-none')
        $('.total-filter').text(totalFields);
    } else {
        $('.span-filter').addClass('d-none');
    }
}

function applyMask(mask, value) {
    let i = 0;
    const numbers = value.replace(/\D/g, '');
    return mask.replace(/#/g, () => numbers[i++] || '');
}

function strReplace(string, subject, replace) {
    return string.split(subject).join(replace);
}

function isFunction(callback) {
    return typeof callback === 'function';
}

function formatWhatsApp(phone) {

    phone = phone.replace(/\D/g, '');

    if (phone.length <= 10) {
        return phone.replace(
            /(\d{2})(\d{4})(\d{0,4})/,
            function(match, p1, p2, p3){
                return '(' + p1 + ') ' + p2 + (p3 ? '-' + p3 : '');
            }
        );
    }

    return phone.replace(
        /(\d{2})(\d{5})(\d{0,4})/,
        function(match, p1, p2, p3){
            return '(' + p1 + ') ' + p2 + (p3 ? '-' + p3 : '');
        }
    );
}

function getSortBy() {
    let $select = $('#sort_select');

    if ($select.length > 0) {
        return $select.val();
    }

    return '';
}

function elementExists(selector) {
    return $(selector).length > 0;
}

function countFields(form) {
    const fields = $(form).find('input, select, textarea');
    const exits = fields.filter(function() {
        return $.trim($(this).val()) !== '';
    });

    return exits.length;
}

function existSelector(selector) {
    if (document.querySelector(selector)) {
        return true;
    }

    return false;
}

function exportContentText() {
    const editor = document.getElementById('editor');
    if (!editor) {
        alert('Editor não encontrado.');
        return '';
    }

    // clone para evitar modificar o editor real
    const clone = editor.cloneNode(true);

    // remover controles e handlers de UI que não fazem parte do conteúdo
    const selectorsToRemove = [
        '.image-controls', '.html-actions', '.col-handle', '.table-settings', '.table-picker', '.color-palette', '.html-modal',
        '.img-handle', '.image-toolbar', '.image-toolbar button'
    ];
    selectorsToRemove.forEach(sel => {
        clone.querySelectorAll(sel).forEach(n => n.remove());
    });

    // remover atributos contenteditable
    clone.querySelectorAll('[contenteditable]').forEach(n => n.removeAttribute('contenteditable'));

    // limpar classes/atributos criados para edição
    clone.querySelectorAll('.image-wrapper').forEach(w => {
        w.classList.remove('selected');
        w.removeAttribute('tabindex');
        w.style.width = '';
        w.style.margin = '';
        w.style.display = '';
    });

    // remover possíveis resíduos
    clone.querySelectorAll('.img-handle, .image-toolbar, .image-controls').forEach(n => n.remove());
    return clone.innerHTML;
}

function getMomentCurrency() {
    moment.locale('pt-br');
    return moment();
}

function sendRequest(url, type, selectorForm, dataType, callbackSuccess, loading = true, callbackError = null, payload = null) {
    clearFormError(selectorForm);

    if (selectorForm && !validForm(selectorForm)) {
        closeLoadingAjax();
        return
    }

    let formData = selectorForm ? $(selectorForm).serialize() : '';

    if (payload) {
        formData += '&' + $.param(payload);
    }

    formData += '&sort=' + getSortBy();

    $.ajax({
        url: url,
        type: type,
        dataType: dataType,
        data: formData,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        beforeSend: function () {
            clearFormError(selectorForm);
            if (loading) {
                loadingAjax();
            }
        },
        success: function(response) {
            closeLoadingAjax();
            if (response.title && response.message && !isFunction(callbackSuccess)) {
                successResponseJson(response.title, response.message)
            }
            if (isFunction(callbackSuccess)) {
                callbackSuccess(response);
            }
        },
        error: function(jqXHR) {
            closeLoadingAjax();
            if (isFunction(callbackError)) {
                callbackError(jqXHR);
                return;
            }
            errorResponseJson(jqXHR, selectorForm);
        },
    });
}

function escapeSelectorString(selector) {
    return selector.replace(/([!"#$%&'()*+,.\/:;<=>?@[\]^`{|}~])/g, '\\$1');
}

function sendRequestByJson(url, type, json, callbackSuccess, callbackError = null) {
    $.ajax({
        url: url,
        type: type,
        dataType: 'json',
        data: json,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        beforeSend: function () {
            clearFormError();
            loadingAjax();
        },
        success: function (response) {
            closeLoadingAjax();
            if (response.title && response.message && !isFunction(callbackSuccess)) {
                successResponseJson(response.title, response.message)
            }
            if (isFunction(callbackSuccess)) {
                callbackSuccess(response);
            }
        },
        error: function (jqXHR) {
            closeLoadingAjax();

            if (isFunction(callbackError)) {
                callbackError(jqXHR);
                return;
            }
            errorResponseJson(jqXHR, '');
        }
    });
}

function generateFilename(extension = 'bin') {
    const now = new Date();

    const pad = (n, size = 2) => String(n).padStart(size, '0');

    const date =
        now.getFullYear() +
        pad(now.getMonth() + 1) +
        pad(now.getDate());

    const time =
        pad(now.getHours()) +
        pad(now.getMinutes()) +
        pad(now.getSeconds()) +
        pad(now.getMilliseconds(), 3);

    const random = Math.random().toString(36).slice(2, 8);
    return `${date}-${time}-${random}.${extension}`;
}

function addElement(selector, html) {
    if ($(selector).length > 0) {
        $(selector).after(html);
    } else {
        console.error('O seletor não corresponde a nenhum elemento.');
    }
}
function clearForm() {
    $('.invalid-feedback').css('display', 'none').html('');
    $('.invalid-feedback').not('.not-remove').remove();
    $('.message-error').remove();
    $('input, textarea, select').removeClass('is-invalid');
    $('input, textarea, select').val('');
}

function loadingFile(urlFile, callbackSuccess, callbackError) {
    fetch(urlFile)
        .then(async (response) => {
            if (!response.ok) {
                const errorText = await response.text();

                console.error('Erro ao carregar:', {
                    status: response.status,
                    body: errorText
                });

                throw new Error('Não foi possível carregar o arquivo.');
            }

            return response.text();
        })
        .then(response => {
            if (isFunction(callbackSuccess)) {
                callbackSuccess(response);
            }
        })
        .catch(error => {
            if (isFunction(callbackError)) {
                callbackError(error);
            }
        });
}

const APP_DIALOG_ICONS = {
    success: '<polyline points="20 6 9 17 4 12"/>',
    info: '<line x1="12" y1="10.5" x2="12" y2="19"/><line x1="12" y1="5.5" x2="12.01" y2="5.5"/>',
    warning: '<line x1="12" y1="4.5" x2="12" y2="14"/><line x1="12" y1="19.5" x2="12.01" y2="19.5"/>',
    error: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
    danger: '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
    lock: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
};

// Ícones nativos do SweetAlert ('question' etc.) mapeados para o tom do diálogo.
const APP_DIALOG_TONES = { success: 'success', info: 'info', question: 'info', warning: 'warning', error: 'error', danger: 'danger', lock: 'lock' };

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text === null || text === undefined ? '' : String(text);
    return div.innerHTML;
}

function appDialogIcon(tone) {
    const content = tone === 'loading'
        ? '<span class="spinner-border" role="status" aria-hidden="true"></span>'
        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">'
            + APP_DIALOG_ICONS[tone] + '</svg>';

    return '<div class="app-dialog-icon app-dialog-icon-' + tone + '" aria-hidden="true">' + content + '</div>';
}

/**
 * Diálogo no padrão visual do sistema (o mesmo do desbloqueio dos prontuários): ícone em círculo,
 * título, texto, botão principal largo e "Cancelar" como link logo abaixo.
 * - tone: success | info | warning | error | danger | lock | loading
 * - text é escapado; html só com conteúdo montado no front-end (nunca resposta da API).
 * - content: marcação extra abaixo do texto (campos, checkbox, imagem).
 * - Sem cancelText o diálogo tem só o botão principal. Demais opções vão direto ao Swal.fire
 *   (preConfirm, didOpen, input, timer, allowOutsideClick...).
 */
function appDialog(options = {}) {
    const {
        tone: toneOption = 'info',
        title = '',
        text = '',
        html = '',
        content = '',
        confirmText = 'Ok',
        confirmIcon = '',
        cancelText = '',
        denyText = '',
        footer = '',
        wide = false,
        popupClass = '',
        ...swalOptions
    } = options;

    const tone = toneOption === 'loading' ? 'loading' : (APP_DIALOG_TONES[toneOption] || 'info');
    const danger = tone === 'danger';
    const message = html || (text ? escapeHtml(text) : '');

    return Swal.fire(Object.assign({
        html: appDialogIcon(tone)
            + (title ? '<h2 class="app-dialog-title">' + escapeHtml(title) + '</h2>' : '')
            + (message ? '<div class="app-dialog-text">' + message + '</div>' : '')
            + (content ? '<div class="app-dialog-content">' + content + '</div>' : ''),
        footer: footer || undefined,
        confirmButtonText: (confirmIcon ? '<i class="' + confirmIcon + ' me-2"></i>' : '') + escapeHtml(confirmText),
        showCancelButton: !!cancelText,
        cancelButtonText: escapeHtml(cancelText),
        showDenyButton: !!denyText,
        denyButtonText: escapeHtml(denyText),
        // Em ações destrutivas o foco fica no "Cancelar": um Enter acidental não apaga nada.
        focusCancel: danger && !!cancelText,
        buttonsStyling: false,
        customClass: {
            popup: ['app-dialog', wide ? 'app-dialog-wide' : '', popupClass].join(' ').trim(),
            confirmButton: 'btn ' + (danger ? 'btn-danger' : 'btn-primary') + ' app-dialog-confirm',
            cancelButton: 'btn btn-link app-dialog-cancel',
            denyButton: 'btn btn-link app-dialog-deny',
            footer: 'app-dialog-footer',
            input: 'app-dialog-input',
            inputLabel: 'app-dialog-label',
        },
    }, swalOptions));
}

/**
 * Troca o botão principal por "carregando" (ou restaura o texto original) enquanto o preConfirm trabalha.
 */
function setAppDialogBusy(busy, busyText = 'Aguarde…') {
    const button = Swal.getConfirmButton();
    if (!button) return;

    if (busy) {
        button.dataset.idleHtml = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + escapeHtml(busyText);
        Swal.disableButtons();
        return;
    }

    if (button.dataset.idleHtml) {
        button.innerHTML = button.dataset.idleHtml;
    }
    Swal.enableButtons();
}

// Chama a atenção para o erro mostrado dentro do diálogo.
function shakeAppDialog() {
    const popup = Swal.getPopup();
    if (!popup) return;

    popup.classList.remove('app-dialog-shake');
    void popup.offsetWidth; // reinicia a animação
    popup.classList.add('app-dialog-shake');
}

function confirmAction(html, callback, icon = 'warning', confirmText = 'Confirmar', cancelText = 'Cancelar', title = 'Antes de continuar') {
    appDialog({
        tone: icon,
        title: title,
        html: html,
        confirmText: confirmText,
        cancelText: cancelText,
    }).then((result) => {
        if (result.isConfirmed && isFunction(callback)) {
            callback();
        }
    });
}

function clearFormError(selectorForm) {

    if (!selectorForm) {
        return;
    }

    $('.invalid-feedback').css('display', 'none').html('');
    $('.invalid-feedback').not('.not-remove').remove();
    $('.message-error').remove();
    $(selectorForm).find('input, textarea, select').removeClass('is-invalid');
}

function displayMessageErrorInput(selectorForm, elem, message) {
    var name = $(elem).attr('name');
    name = name.replace(/([!"#$%&'()*+,.\/:;<=>?@[\]^`{|}~])/g, '\\$1')

    var html = '<div class="invalid-feedback invalid-feedback-'+name+'">' + message  + "</div>";
    $(elem).addClass('is-invalid');
    if (!elementExists('.invalid-feedback-'+name)) {
        addElement(elem, html)
    } else {
        $('.invalid-feedback-'+name).css('display', 'block').html(message);
    }
}

function cleanMessageErrorInput(name, elem) {
    $('.invalid-feedback-' + name).css('display', 'none').html('');
    $('.invalid-feedback-' + name).not('.not-remove').remove().removeClass('d-block');
    $(elem).removeClass('is-invalid');
}

function addMessageErrorInput(elem, name, message) {
    var html = '<div class="invalid-feedback invalid-feedback-'+name+'">' + message  + "</div>";
    $(elem).addClass('is-invalid');
    if (!elementExists('.invalid-feedback-'+name)) {
        addElement(elem, html)
    } else {
        $('.invalid-feedback-'+name).text(message).addClass('d-block');
    }
}

function validForm(selectorForm) {
    var required = true;
    var message = "Este campo é obrigatório."

    $(selectorForm + ' input').each(function() {
        if ($(this).attr('required') && (!$(this).val() || !$(this).val().trim())) {
            required = false;
            displayMessageErrorInput(
                selectorForm,
                this,
                message
            )
        }
    });
    $(selectorForm + ' select').each(function() {
        if ($(this).attr('required') && !Array.isArray($(this).val()) && (!$(this).val() || !$(this).val().trim())) {
            required = false;
            displayMessageErrorInput(
                selectorForm,
                this,
                message
            )

        }
        else if ($(this).attr('required') && $(this).hasClass('required') && Array.isArray($(this).val()) && $(this).val().length <= 0) {
            required = false;
            var name = $(this).attr('name');
            name = strReplace(name, '[', '');
            name = strReplace(name, ']', '');
            displayMessageErrorInput(
                selectorForm,
                this,
                message
            )

        }
    });
    $(selectorForm + ' textarea').each(function() {
        if ($(this).attr('required') && (!$(this).val() || !$(this).val().trim())) {
            required = false;
            displayMessageErrorInput(
                selectorForm,
                this,
                message
            )
        }
    });

    return required;
}

let sessionExpiredHandled = false;

function isSessionExpired(jqXHR) {
    return !!jqXHR && jqXHR.status === 401;
}

// fetch sem Accept JSON recebe 302 para o login (seguido automaticamente, vira 200 com HTML).
function isSessionExpiredResponse(response) {
    if (response.status === 401) return true;

    return response.redirected && new URL(response.url).pathname.indexOf('/login') === 0;
}

// Sessão expirada: preserva o rascunho do prontuário, avisa o usuário e envia ao login.
function handleSessionExpired() {
    // USER_UUID só existe no layout autenticado; telas públicas/guest não redirecionam.
    if (sessionExpiredHandled || typeof window.USER_UUID === 'undefined') return;
    sessionExpiredHandled = true;

    if (window.MedicalEditor && MedicalEditor.isReady()) {
        MedicalEditor.saveDraftNow();
    }

    appDialog({
        tone: 'lock',
        title: 'Sua sessão expirou',
        text: 'Por segurança, entre novamente para continuar.',
        confirmText: 'Ir para o login',
        allowOutsideClick: false,
        allowEscapeKey: false,
        timer: 4000,
        timerProgressBar: true
    }).then(function () {
        redirectTo('/login?code=session-expired&redirect=' + encodeURIComponent(location.pathname + location.search));
    });
}

$(document).ajaxError(function (event, jqXHR, settings) {
    if (!isSessionExpired(jqXHR) || (settings.url || '').indexOf('/login') === 0) return;
    handleSessionExpired();
});

function errorResponseJson(err, selectorForm) {

    console.log('err -->', err.responseJSON);
    const messageWarning = 'Não foi possível concluir';

    if (isSessionExpired(err)) {
        handleSessionExpired();
        return;
    }

    // Fluxo público expirado (2FA, link de redefinição): avisa e envia ao login.
    if (err.status === 410 && err.responseJSON && err.responseJSON.link) {
        appDialog({
            tone: 'warning',
            title: 'Este link expirou',
            text: err.responseJSON.message,
            confirmText: 'Ok',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then(function () {
            redirectTo(err.responseJSON.link);
        });
        return;
    }

    // Limite mensal do plano atingido: oferece o upgrade.
    if (err.status === 403 && err.responseJSON && err.responseJSON.link) {
        appDialog({
            tone: 'warning',
            title: 'Seu plano chegou ao limite',
            text: err.responseJSON.message,
            confirmText: 'Fazer upgrade',
            cancelText: 'Agora não'
        }).then(function (result) {
            if (result.isConfirmed) {
                redirectTo(err.responseJSON.link);
            }
        });
        return;
    }

    if (err.message) {
        clickWarningMessage(messageWarning, err.message);
        return;
    }

    if(err.responseJSON && err.responseJSON.errors) {

        var html;
        var message;

        for(var key in err.responseJSON.errors) {

            // Campos aninhados: "holidays.12.date" → name="holidays[12][date]"
            var fieldName = key.replace(/\.([^.]+)/g, '[$1]');
            var fieldSelector = selectorForm + ' [name="'+fieldName+'"]';
            var feedbackKey = key.replace(/[^\w-]/g, '-');

            html = '<div class="invalid-feedback invalid-feedback-'+feedbackKey+'">';
            message = '';

            for(var j in err.responseJSON.errors[key]) {
                message += err.responseJSON.errors[key][j] + '<br/>';
            }

            html +=  message +'</div>';
            $(fieldSelector).addClass('is-invalid');

            if (!elementExists('.invalid-feedback-'+feedbackKey)) {
                addElement(fieldSelector, html)
            } else {
                $('.invalid-feedback-'+feedbackKey).css('display', 'block').html(message);
            }
        }
    } else  if (err.responseJSON && err.responseJSON.message) {
        clickWarningMessage(messageWarning, err.responseJSON.message);
        return;
    } else if (err.responseJSON) {

        var html;
        var message;

        for(var key in err.responseJSON) {

            html = '<div class="invalid-feedback invalid-feedback-'+key+'">';
            message = '';

            for(var j in err.responseJSON[key]) {
                message += err.responseJSON[key][j] + '<br/>';
            }

            html +=  message +'</div>';
            $(selectorForm + ' [name='+key+']').addClass('is-invalid');

            if (!elementExists('.invalid-feedback-'+key)) {
                addElement(selectorForm + ' [name='+key+']', html)
            } else {
                $('.invalid-feedback-'+key).css('display', 'block').html(message);
            }
        }
    }
}

function sendLog(message, route) {

    $.ajax({
        url: '/logs',
        type: 'POST',
        dataType: 'json',
        data: {
            message: message,
            route: route
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
}

function displayError(message) {
    return '<div class="alert alert-contrast message-error alert-danger alert-dismissible mt-4" role="alert"><div class="icon"> <i class="fa-regular fa-face-frown"></i></div><div class="message"><button class="close" type="button" data-dismiss="alert" aria-label="Close"><span class="mdi mdi-close" aria-hidden="true"></span></button>'+message+'</div></div>'
}

function displaySuccess(message) {
    return '<div class="alert message-error alert-success alert-dismissible mt-4" role="alert"><button class="close" type="button" data-dismiss="alert" aria-label="Close"><span class="mdi mdi-close" aria-hidden="true"></span></button> <div class="icon"><span class="mdi mdi-check"></span></div> <div class="message"><strong>Finalizado!</strong> '+message+'</div></div>'
}

function displayWarning(message) {
    return '<div class="alert alert-contrast message-error alert-warning alert-dismissible mt-4" role="alert"><div class="icon"> <i class="fa-solid fa-triangle-exclamation"></i></div><div class="message"><button class="close" type="button" data-dismiss="alert" aria-label="Close"><span class="mdi mdi-close" aria-hidden="true"></span></button>'+message+'</div></div>'
}
function getURL() {
    return window.location.href;
}
function loadingAjax() {

    $('.be-content').addClass('loading-op')

    appDialog({
        tone: 'loading',
        title: 'Só um instante',
        text: 'Estamos processando…',
        showConfirmButton: false,
        allowOutsideClick: false,
        popupClass: 'app-dialog-loading',
    }).then((result) => {
        if (result.dismiss) {
            $('.be-content').removeClass('loading-op');
        }
    });
}

function closeLoadingAjax() {
    // Só fecha o loading: outro popup aberto (ex.: toast) não pode ser derrubado por uma requisição que terminou.
    const popup = Swal.getPopup();
    if (popup && popup.classList.contains('app-dialog-loading')) {
        Swal.close();
    }
    $('.be-content').removeClass('loading-op');
}

function redirectTo(url) {
    loadingAjax();
    window.location.href = url;
}

function successResponseJson(title, message, timout = null) {

    const show = function () {
        return appDialog({ tone: 'success', title: title, text: message });
    };

    if (timout) {
        setTimeout(show, timout);
        return;
    }
    return show();
}

/**
 * Toast padrão do sistema: canto superior direito, fecha sozinho com barra de progresso e sem animação.
 * icon: 'success' (verde), 'error' (vermelho) ou 'info' (azul); options sobrescreve qualquer opção do Swal (ex.: html, didOpen).
 */
function showToast(icon, title, options = {}) {
    const backgrounds = { success: '#146c43', error: '#b02a37', info: '#0a58ca' };

    return Swal.fire(Object.assign({
        toast: true,
        position: 'top-end',
        icon: icon,
        title: title,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        color: '#ffffff',
        background: backgrounds[icon] || backgrounds.success,
        iconColor: '#ffffff',
        showClass: { popup: '', icon: '' },
        hideClass: { popup: '' },
    }, options));
}

/**
 * Toast de sucesso usado nos retornos de redirect ?m=.
 */
function toastSuccess(message) {
    return showToast('success', message);
}

/**
 * Remove parâmetros da URL depois de exibir o aviso, para não repeti-lo no F5.
 */
function clearQueryParams(...keys) {
    const url = new URL(window.location.href);
    keys.forEach(function (key) { url.searchParams.delete(key); });
    history.replaceState(null, '', url.pathname + url.search + url.hash);
}

function copyToClipboard(text, title = 'Copiado', callbackSuccess = null) {
    const onSuccess = function () {
        showToast('success', title);
        if (typeof callbackSuccess === 'function') callbackSuccess();
    };
    const onError = function () {
        showToast('error', 'Não foi possível copiar. Selecione o texto e copie manualmente.');
    };

    // navigator.clipboard só existe em HTTPS e pode ser negado; nesses casos usa o método antigo
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(onSuccess).catch(function () {
            copyToClipboardFallback(text) ? onSuccess() : onError();
        });
        return;
    }

    copyToClipboardFallback(text) ? onSuccess() : onError();
}

function copyToClipboardFallback(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();

    let copied = false;
    try {
        copied = document.execCommand('copy');
    } catch (e) {
        copied = false;
    }

    document.body.removeChild(textarea);
    return copied;
}

function successResponseWithTimer(title, message, icon) {

    appDialog({
        tone: icon,
        title: title,
        html: message,
        showConfirmButton: false,
        timer: 1500
    });
}

function gerarUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

function clickWarningMessage(title, message) {
    return appDialog({ tone: 'warning', title: title, text: message });
}

function clickErrorMessage(title, message) {
    return appDialog({ tone: 'error', title: title, text: message });
}

function getId() {
    var id = $("#inputId")

    if (id.length > 0 && id.val().trim()) {
        return id.val().trim();
    }

    return null
}

/**
 * Ao clicar na lupa consulta o endpoint e exibe abaixo do input se o endereço
 * (slug) está disponível (check verde) ou indisponível (x vermelho).
 */
function bindSlugAvailability(selectorInput, selectorButton, selectorResult, url) {
    if (!elementExists(selectorInput) || !elementExists(selectorButton)) return;

    let timer = null;

    $(selectorButton).on('click', function () {
        clearTimeout(timer);
        checkSlugAvailability(url, $(selectorInput).val().trim(), selectorResult);
    });

    // Ao parar de digitar consulta automaticamente (ignora endereços com menos de 3 caracteres)
    $(selectorInput).on('input', function () {
        clearTimeout(timer);

        const slug = $(this).val().trim();
        if (slug.length < 3) return;

        timer = setTimeout(function () {
            checkSlugAvailability(url, slug, selectorResult);
        }, 600);
    });
}

function checkSlugAvailability(url, slug, selectorResult) {
    const $result = $(selectorResult);
    const $button = $('#btn-check-slug');

    const showResult = function (available, message) {
        const icon = available ? 'fa-check-circle text-success' : 'fa-times-circle text-danger';
        const color = available ? 'text-success' : 'text-danger';

        $result
            .removeClass('d-none text-muted text-success text-danger')
            .addClass(color)
            .html('<i class="fas ' + icon + ' me-1"></i>')
            .append(document.createTextNode(message));
    };

    if (slug.length === 0) {
        showResult(false, 'Informe o endereço da página.');
        return;
    }

    // Ignora resposta atrasada se o endereço mudou durante a requisição
    const isOutdated = function () {
        return $('#slug').val().trim() !== slug;
    };

    $.ajax({
        url: url,
        type: 'GET',
        dataType: 'json',
        data: { slug: slug },
        beforeSend: function () {
            $button.prop('disabled', true);
            $result
                .removeClass('d-none text-success text-danger')
                .addClass('text-muted')
                .html('<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Verificando disponibilidade...');
        },
        success: function (response) {
            if (isOutdated()) return;
            showResult(response.available, response.message);
        },
        error: function (jqXHR) {
            if (isOutdated()) return;

            const errors = jqXHR.responseJSON && jqXHR.responseJSON.errors;
            const message = errors && errors.slug
                ? errors.slug[0]
                : 'Não foi possível verificar o endereço. Tente novamente.';

            showResult(false, message);
        },
        complete: function () {
            $button.prop('disabled', false);
        },
    });
}

function initPatientFilter() {
    $('#form-search #patient_id').select2({
        language: 'pt-BR',
        placeholder: 'Pesquisar paciente...',
        allowClear: true,
        minimumInputLength: 2,
        dropdownParent: $('#filterModal'),
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

// Mostrar/ocultar senha: botão .btn-toggle-password ao lado do input (login, cadastro, perfil).
$(document).on('click', '.btn-toggle-password', function () {
    const input = $(this).siblings('input');
    const show = input.attr('type') === 'password';
    input.attr('type', show ? 'text' : 'password');
    $(this).find('i').toggleClass('fa-eye', !show).toggleClass('fa-eye-slash', show);
});
