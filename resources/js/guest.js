function showRecoveryError(jqXHR, selectorForm, selectorField) {
    clearFormError(selectorForm);

    const response = jqXHR.responseJSON || {};
    const isDomainError = !response.errors && (jqXHR.status === 400 || jqXHR.status === 429);

    if (!isDomainError) {
        return errorResponseJson(jqXHR, selectorForm);
    }

    const message = jqXHR.status === 429
        ? 'Muitas tentativas. Aguarde alguns instantes e tente novamente.'
        : response.message;

    if (selectorField) {
        const field = $(selectorForm + ' ' + selectorField);
        field.addClass('is-invalid');
        field.after('<div class="invalid-feedback" style="display:block;">' + message + '</div>');
        field.trigger('focus');
        return;
    }

    $(selectorForm).prepend('<div class="alert alert-danger message-error" role="alert">' + message + '</div>');
}

let twoFactorChallengeSubmitting = false;

function isTwoFactorRecoveryMode() {
    return !$('#two-factor-recovery-group').hasClass('d-none');
}

function submitTwoFactorChallenge() {
    const isRecovery = isTwoFactorRecoveryMode();
    const code = isRecovery ? $('#two-factor-recovery-code').val().trim() : getOtpValue('#two-factor-otp');

    if (twoFactorChallengeSubmitting) {
        return;
    }

    if (!isRecovery && code.length !== 6) {
        showOtpError('#two-factor-otp', 'Informe os 6 dígitos do código.');
        return;
    }

    if (isRecovery && !code) {
        $('#two-factor-recovery-code').trigger('focus');
        return;
    }

    twoFactorChallengeSubmitting = true;
    $('#code-value').val(code);

    sendRequest(
        '/two-factor/challenge',
        'POST',
        '#form-two-factor-challenge',
        'json',
        function(response) {
            return redirectTo(response.link);
        },
        true,
        function(jqXHR) {
            twoFactorChallengeSubmitting = false;

            if (isRecovery) {
                return showRecoveryError(jqXHR, '#form-two-factor-challenge', '#two-factor-recovery-code');
            }

            // Código inválido ou excesso de tentativas aparecem abaixo dos dígitos; o resto (ex.: sessão expirada) segue o padrão
            if (jqXHR.status !== 400 && jqXHR.status !== 429) {
                return errorResponseJson(jqXHR, '#form-two-factor-challenge');
            }

            showOtpError('#two-factor-otp', twoFactorChallengeErrorMessage(jqXHR));
        }
    );
}

function twoFactorChallengeErrorMessage(jqXHR) {
    if (jqXHR.status === 429) {
        return 'Muitas tentativas. Aguarde alguns instantes e tente novamente.';
    }

    const response = jqXHR.responseJSON || {};
    const first = Object.values(response)[0];

    return response.message || (Array.isArray(first) ? first[0] : 'Não foi possível verificar o código. Tente novamente.');
}

function toggleTwoFactorRecovery(isRecovery) {
    clearFormError('#form-two-factor-challenge');
    clearOtp('#two-factor-otp');

    $('#two-factor-otp-group').toggleClass('d-none', isRecovery);
    $('#two-factor-recovery-group').toggleClass('d-none', !isRecovery);
    $('#btn-use-recovery-code').toggleClass('d-none', isRecovery);
    $('#btn-use-app-code').toggleClass('d-none', !isRecovery);
    $('#two-factor-description').text(isRecovery
        ? 'Informe um dos códigos de recuperação gerados ao ativar o 2FA.'
        : 'Informe o código de 6 dígitos exibido no Google Authenticator.');

    if (isRecovery) {
        $('#two-factor-recovery-code').val('').trigger('focus');
    } else {
        focusOtp('#two-factor-otp');
    }
}

$(document).ready(function(){
    $('#form-login').submit(function(e) {
        e.preventDefault();
        sendRequest(
            '/login',
            'POST',
            '#form-login',
            'json',
            function(response) {
                return redirectTo(response.link);
            }
        )
    });

    $('#form-two-factor-challenge').submit(function(e) {
        e.preventDefault();
        submitTwoFactorChallenge();
    });

    bindOtpInput('#two-factor-otp', function(code) {
        if (code.length === 6) {
            submitTwoFactorChallenge();
        }
    });

    if (elementExists('#two-factor-otp')) {
        focusOtp('#two-factor-otp');
    }

    $('#btn-use-recovery-code').on('click', function(e) {
        e.preventDefault();
        toggleTwoFactorRecovery(true);
    });

    $('#btn-use-app-code').on('click', function(e) {
        e.preventDefault();
        toggleTwoFactorRecovery(false);
    });

    $('#btn-send-two-factor-reset').on('click', function(e) {
        e.preventDefault();
        sendRequest(
            '/two-factor/challenge/reset-link',
            'POST',
            null,
            'json'
        )
    });

    $('#form-two-factor-reset').submit(function(e) {
        e.preventDefault();
        sendRequest(
            $(this).data('action'),
            'POST',
            null,
            'json',
            function(response) {
                return redirectTo(response.link);
            }
        )
    });

    $('#form-forgot-password').submit(function(e) {
        e.preventDefault();
        sendRequest(
            '/forgot-password',
            'POST',
            '#form-forgot-password',
            'json',
            null,
            true,
            function(jqXHR) {
                showRecoveryError(jqXHR, '#form-forgot-password', '#email');
            }
        )
    });

    $('#form-reset-password').submit(function(e) {
        e.preventDefault();
        sendRequest(
            '/reset-password',
            'POST',
            '#form-reset-password',
            'json',
            function(response) {
                successResponseJson(response.title, response.message).then(function() {
                    return redirectTo(response.link);
                });
            },
            true,
            function(jqXHR) {
                showRecoveryError(jqXHR, '#form-reset-password');
            }
        )
    });

    $('#form-reset-password #email-visible').on('input', function() {
        $('#form-reset-password input[name="email"]').val(this.value);
    });

    $('#form-register').submit(function(e) {
        e.preventDefault();
        sendRequest(
            '/register',
            'POST',
            '#form-register',
            'json',
            function(response) {
                return redirectTo(response.link);
            },
            null,
            null,
            {
                timezone: getTimezone()
            }
        )
    });

    $('#form-request-invite').submit(function(e) {
        e.preventDefault();

        const params = new URLSearchParams(window.location.search);

        sendRequest(
            '/convite',
            'POST',
            '#form-request-invite',
            'json',
            function() {
                $('#form-request-invite, #request-invite-intro').addClass('d-none');
                $('#request-invite-success').removeClass('d-none');
            },
            null,
            null,
            {
                utm_source: params.get('utm_source'),
                utm_medium: params.get('utm_medium'),
                utm_campaign: params.get('utm_campaign')
            }
        )
    });

    if (elementExists('#form-request-invite #whatsapp')) {
        maskWhatsApp();
    }
});
