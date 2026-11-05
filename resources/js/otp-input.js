/**
 * Campo de código de 6 dígitos (componente <x-otp-input>), usado na ativação do 2FA e na verificação do login.
 * onChange(code) é chamado a cada alteração; onEnter(code), se informado, substitui o Enter padrão do formulário.
 */
function bindOtpInput(selector, onChange, onEnter = null) {
    if (!elementExists(selector)) {
        return;
    }

    const $inputs = $(selector + ' input');

    $inputs.on('focus', function() {
        this.select();
    });

    $inputs.on('input', function() {
        const index = $inputs.index(this);
        const digits = this.value.replace(/\D/g, '');

        // Autopreenchimento do teclado (one-time-code) entrega os 6 dígitos de uma vez
        if (digits.length > 1) {
            fillOtp(selector, digits, index);
        } else {
            this.value = digits;

            if (digits) {
                $inputs.eq(index + 1).trigger('focus');
            }
        }

        onOtpChange(selector, onChange);
    });

    $inputs.on('paste', function(e) {
        e.preventDefault();

        const text = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
        fillOtp(selector, text.replace(/\D/g, ''), 0);
        onOtpChange(selector, onChange);
    });

    $inputs.on('keydown', function(e) {
        const index = $inputs.index(this);

        if (e.key === 'Backspace' && !this.value && index > 0) {
            e.preventDefault();
            $inputs.eq(index - 1).val('').trigger('focus');
            onOtpChange(selector, onChange);
        } else if (e.key === 'ArrowLeft' && index > 0) {
            e.preventDefault();
            $inputs.eq(index - 1).trigger('focus');
        } else if (e.key === 'ArrowRight' && index < $inputs.length - 1) {
            e.preventDefault();
            $inputs.eq(index + 1).trigger('focus');
        } else if (e.key === 'Enter' && isFunction(onEnter)) {
            e.preventDefault();
            onEnter(getOtpValue(selector));
        }
    });
}

function fillOtp(selector, digits, start) {
    const $inputs = $(selector + ' input');

    digits.slice(0, $inputs.length - start).split('').forEach(function(digit, i) {
        $inputs.eq(start + i).val(digit);
    });

    $inputs.eq(Math.min(start + digits.length, $inputs.length - 1)).trigger('focus');
}

function onOtpChange(selector, onChange) {
    clearOtpError(selector);

    if (isFunction(onChange)) {
        onChange(getOtpValue(selector));
    }
}

function getOtpValue(selector) {
    return $(selector + ' input').map(function() {
        return this.value;
    }).get().join('');
}

function focusOtp(selector) {
    $(selector + ' input').first().trigger('focus');
}

function clearOtp(selector) {
    $(selector + ' input').val('');
    clearOtpError(selector);
}

function clearOtpError(selector) {
    $(selector).removeClass('is-invalid');
    $(selector + '-error').addClass('d-none').text('');
}

// Destaca o erro, limpa os dígitos e devolve o foco ao primeiro campo
function showOtpError(selector, message) {
    const $otp = $(selector).addClass('is-invalid is-shake');
    setTimeout(function() { $otp.removeClass('is-shake'); }, 400);

    $(selector + ' input').val('');
    $(selector + '-error').text(message).removeClass('d-none');
    focusOtp(selector);
}
