$(document).ready(function() {
    bindTwoFactorWizard();
    bindTwoFactorRecoveryCodes();
    bindTwoFactorManage();
});

let twoFactorRecoveryCodes = [];
let twoFactorConfirming = false;

function twoFactorRequest(url, type, data = {}) {
    return $.ajax({
        url: url,
        type: type,
        dataType: 'json',
        data: data,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
    });
}

function twoFactorErrorMessage(jqXHR) {
    const json = jqXHR.responseJSON || {};

    if (json.message) {
        return json.message;
    }

    const first = Object.values(json)[0];

    return Array.isArray(first) ? first[0] : 'Não foi possível concluir a operação. Tente novamente.';
}

// Pede o código do app (ou de recuperação) antes de ações sensíveis; também usado em medical-records.js.
function promptTwoFactorReauth(options) {
    $('.wrapper').addClass('d-none');

    return appDialog({
        tone: 'lock',
        title: options.title,
        text: options.text,
        content: '<label for="two-factor-code" class="app-dialog-label">Código de verificação</label>' +
            // Aceita os 6 dígitos do aplicativo ou o código de recuperação (xxxxx-xxxxx).
            '<input id="two-factor-code" class="form-control text-center" maxlength="11" autocomplete="one-time-code" ' +
                'autocapitalize="off" autocorrect="off" spellcheck="false" placeholder="123456 ou xxxxx-xxxxx">',
        confirmText: options.confirmText,
        cancelText: 'Cancelar',
        allowOutsideClick: false,
        didOpen: function(popup) {
            const input = popup.querySelector('#two-factor-code');

            input.addEventListener('input', () => Swal.resetValidationMessage());
            input.addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    Swal.clickConfirm();
                }
            });
            input.focus();
        },
        preConfirm: function() {
            const input = Swal.getPopup().querySelector('#two-factor-code');
            const payload = Object.assign({}, options.data, { code: input.value.trim() });

            if (!payload.code) {
                Swal.showValidationMessage('Informe o código do aplicativo ou de recuperação.');
                input.focus();
                return false;
            }

            setAppDialogBusy(true, 'Verificando…');

            return twoFactorRequest(options.url, options.type, payload).catch(function(jqXHR) {
                setAppDialogBusy(false);
                Swal.showValidationMessage(twoFactorErrorMessage(jqXHR));
                shakeAppDialog();
                input.focus();
                input.select();
                return false;
            });
        },
    });
}

/**
 * Assistente de ativação (tela /two-factor com 2FA inativo)
 */
function bindTwoFactorWizard() {
    if (!elementExists('#tf-wizard')) {
        return;
    }

    $('#tf-start').on('click', startTwoFactorSetup);

    $('[data-tf-go]').on('click', function() {
        goToTwoFactorStep(Number($(this).data('tf-go')));
    });

    $('#tf-copy-secret').on('click', function() {
        copyToClipboard($('#tf-secret').text().replace(/\s/g, ''), 'Chave copiada');
    });

    $('#tf-confirm').on('click', confirmTwoFactor);

    bindOtpInput('#tf-otp', onTwoFactorOtpChange, confirmTwoFactor);
}

function goToTwoFactorStep(step) {
    $('[data-tf-step]').addClass('d-none');
    $('[data-tf-step="' + step + '"]').removeClass('d-none');

    $('[data-tf-indicator]').each(function() {
        const indicator = Number($(this).data('tf-indicator'));

        $(this)
            .toggleClass('is-done', indicator < step)
            .toggleClass('is-current', indicator === step)
            .attr('aria-current', indicator === step ? 'step' : null);
    });

    if (step === 3) {
        focusOtp('#tf-otp');
    }
}

function startTwoFactorSetup() {
    // Reaproveita o QR já gerado: um novo setup invalidaria o que o usuário pode já ter escaneado.
    if ($('#tf-qr').children().length) {
        goToTwoFactorStep(2);
        return;
    }

    const $button = $('#tf-start').prop('disabled', true);

    twoFactorRequest('/two-factor/setup', 'POST').done(function(response) {
        $('#tf-qr').html(response.qr_code);
        $('#tf-secret').text(response.secret);
        goToTwoFactorStep(2);
    }).fail(function(jqXHR) {
        clickWarningMessage('Não foi possível concluir', twoFactorErrorMessage(jqXHR));
    }).always(function() {
        $button.prop('disabled', false);
    });
}

function onTwoFactorOtpChange(code) {
    $('#tf-confirm').prop('disabled', code.length !== 6 || twoFactorConfirming);

    if (code.length === 6) {
        confirmTwoFactor();
    }
}

function confirmTwoFactor() {
    const code = getOtpValue('#tf-otp');

    if (code.length !== 6 || twoFactorConfirming) {
        return;
    }

    twoFactorConfirming = true;
    const $button = $('#tf-confirm');
    const label = $button.html();
    $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Verificando...');

    twoFactorRequest('/two-factor/confirm', 'POST', { code: code }).done(function(data) {
        $('#tf-status')
            .removeClass('dash-chip-warning')
            .addClass('dash-chip-success')
            .html('<i class="fa-solid fa-check" aria-hidden="true"></i> Ativa');

        renderTwoFactorRecoveryCodes(data.recovery_codes);
        goToTwoFactorStep(4);
    }).fail(function(jqXHR) {
        showOtpError('#tf-otp', twoFactorErrorMessage(jqXHR));
    }).always(function() {
        twoFactorConfirming = false;
        $button.html(label).prop('disabled', getOtpValue('#tf-otp').length !== 6);
    });
}

/**
 * Códigos de recuperação (fim do assistente e "Gerar novos")
 */
function bindTwoFactorRecoveryCodes() {
    if (!elementExists('#tf-recovery')) {
        return;
    }

    $('#tf-copy-codes').on('click', function() {
        copyToClipboard(twoFactorRecoveryCodes.join('\n'), 'Códigos copiados');
    });

    $('#tf-download-codes').on('click', downloadTwoFactorRecoveryCodes);

    $('#tf-saved-codes').on('change', function() {
        $('#tf-finish').prop('disabled', !this.checked);
    });

    $('#tf-finish').on('click', function() {
        twoFactorRecoveryCodes = [];
        window.location.reload();
    });

    // Evita sair sem guardar os códigos: eles não são exibidos novamente
    $(window).on('beforeunload', function(e) {
        if (twoFactorRecoveryCodes.length && !$('#tf-saved-codes').is(':checked')) {
            e.preventDefault();
            return '';
        }
    });
}

function renderTwoFactorRecoveryCodes(codes) {
    twoFactorRecoveryCodes = codes;

    const $list = $('#tf-recovery-list').empty();
    codes.forEach(function(code) {
        $('<li>').text(code).appendTo($list);
    });

    $('#tf-saved-codes').prop('checked', false);
    $('#tf-finish').prop('disabled', true);
}

function downloadTwoFactorRecoveryCodes() {
    const content = [
        'PSI4 - Códigos de recuperação da autenticação de 2 fatores',
        'Conta: ' + $('#tf-recovery').data('account'),
        'Gerados em: ' + new Date().toLocaleString('pt-BR'),
        '',
        'Cada código pode ser usado uma única vez.',
        '',
    ].concat(twoFactorRecoveryCodes).join('\n');

    const url = URL.createObjectURL(new Blob([content], { type: 'text/plain;charset=utf-8' }));
    const link = $('<a>').attr({ href: url, download: 'psi4-codigos-de-recuperacao.txt' }).appendTo('body');

    link[0].click();
    link.remove();
    URL.revokeObjectURL(url);
}

/**
 * Gerenciamento (tela /two-factor com 2FA ativo)
 */
function bindTwoFactorManage() {
    if (!elementExists('#tf-manage')) {
        return;
    }

    $('#tf-regenerate').on('click', regenerateRecoveryCodes);
    $('#tf-disable').on('click', disableTwoFactor);
}

function regenerateRecoveryCodes() {
    promptTwoFactorReauth({
        title: 'Novos códigos de recuperação',
        text: 'Os códigos anteriores deixarão de funcionar. Informe o código do aplicativo ou de recuperação.',
        confirmText: 'Gerar códigos',
        url: '/two-factor/recovery-codes',
        type: 'POST',
    }).then(function(result) {
        $('.wrapper').removeClass('d-none');

        if (!result.isConfirmed) {
            return;
        }

        const codes = result.value.recovery_codes;

        renderTwoFactorRecoveryCodes(codes);
        $('#tf-codes-left').removeClass('dash-chip-warning').addClass('dash-chip-muted').text(codes.length + ' restantes');
        $('#tf-regenerate').prop('disabled', true);
        $('#tf-regenerated').removeClass('d-none')[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

function disableTwoFactor() {
    promptTwoFactorReauth({
        title: 'Desativar autenticação de 2 fatores',
        text: 'Sua conta voltará a ser protegida apenas pela senha. Informe o código do aplicativo ou de recuperação para desativar.',
        confirmText: 'Desativar',
        url: '/two-factor',
        type: 'DELETE',
    }).then(function(result) {
        $('.wrapper').removeClass('d-none');

        if (!result.isConfirmed) {
            return;
        }

        successResponseJson('Tudo certo', 'A autenticação de 2 fatores foi desativada.').then(function() {
            window.location.reload();
        });
    });
}
