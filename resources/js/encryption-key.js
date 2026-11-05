// Senha mestre dos prontuários: configuração, troca, recuperação e nova chave de recuperação.
// Toda a criptografia acontece aqui no navegador (crypto.js); o servidor só recebe os embrulhos da chave.
$(document).ready(function() {
    if (!elementExists('#encryption-key-page')) {
        return;
    }

    $('#ek-start').on('click', () => goToEncryptionKeyStep(2));
    $('#form-store-encryption-key').on('submit', function(e) {
        e.preventDefault();
        prepareEncryptionKey(this);
    });
    $('#ek-finish').on('click', storeEncryptionKey);
    $('#ek-saved').on('change', function() {
        $('#ek-finish').prop('disabled', !this.checked);
    });

    $('#form-update-passphrase').on('submit', function(e) {
        e.preventDefault();
        updatePassphraseEncryptionKey(this);
    });
    $('#form-recover-encryption-key').on('submit', function(e) {
        e.preventDefault();
        recoverEncryptionKey(this);
    });
    $('#form-update-recovery-key').on('submit', function(e) {
        e.preventDefault();
        updateRecoveryEncryptionKey(this);
    });
    $('#ek-lock').on('click', lockMedicalRecords);

    $(document).on('click', '.ek-download', function() {
        downloadRecoveryKit($(this).data('target'));
    });
    $(document).on('click', '.ek-copy', function() {
        copyToClipboard($($(this).data('target')).text(), 'Chave copiada');
    });

    if (new URLSearchParams(window.location.search).get('mode') === 'recover' && elementExists('#ek-recover')) {
        bootstrap.Collapse.getOrCreateInstance(document.getElementById('ek-recover-body'), { toggle: false }).show();
        document.getElementById('ek-recover').scrollIntoView({ behavior: 'smooth' });
    }
});

let pendingEncryptionKey = null;

function goToEncryptionKeyStep(step) {
    $('[data-ek-step]').addClass('d-none');
    $('[data-ek-step="' + step + '"]').removeClass('d-none');
    $('[data-ek-indicator]').each(function() {
        const indicator = Number($(this).data('ek-indicator'));
        $(this).toggleClass('is-done', indicator < step).toggleClass('is-current', indicator === step);
    });
}

// Valida a senha nova + confirmação de um formulário; devolve a senha ou null (exibindo o erro no campo).
function readNewPassphrase(form, field = 'passphrase') {
    const $form = $(form);
    const passphrase = $form.find('[name="' + field + '"]').val() || '';
    const confirmation = $form.find('[name="' + field + '_confirmation"]').val() || '';

    clearFormError(form);

    if (passphrase.length < MEDICAL_CRYPTO.minPassphraseLength) {
        showFieldError(form, field, 'A senha mestre deve ter pelo menos ' + MEDICAL_CRYPTO.minPassphraseLength + ' caracteres.');
        return null;
    }

    if (passphrase !== confirmation) {
        showFieldError(form, field + '_confirmation', 'As senhas não conferem.');
        return null;
    }

    return normalizePassphrase(passphrase);
}

function showFieldError(form, field, message) {
    const $input = $(form).find('[name="' + field + '"]');
    $input.addClass('is-invalid');
    $input.after('<div class="invalid-feedback d-block">' + $('<div>').text(message).html() + '</div>');
    $input.trigger('focus');
}

async function withLoading(callback) {
    loadingAjax();

    try {
        return await callback();
    } finally {
        closeLoadingAjax();
    }
}

// Passo 2 -> 3: gera a chave dos prontuários e a chave de recuperação (ainda nada é enviado).
async function prepareEncryptionKey(form) {
    const passphrase = readNewPassphrase(form);
    if (!passphrase) return;

    try {
        pendingEncryptionKey = await withLoading(async () => {
            const raw = crypto.getRandomValues(new Uint8Array(32));
            const recoveryKey = generateRecoveryKey();
            const passphraseWrap = await wrapRecordKey(raw, passphrase);
            const recoveryWrap = await wrapRecordKey(raw, normalizeRecoveryKey(recoveryKey));

            return {
                raw: raw,
                recoveryKey: recoveryKey,
                payload: {
                    passphrase_wrapped_key: passphraseWrap.wrapped,
                    passphrase_salt: passphraseWrap.salt,
                    passphrase_iterations: passphraseWrap.iterations,
                    recovery_wrapped_key: recoveryWrap.wrapped,
                    recovery_salt: recoveryWrap.salt,
                    recovery_iterations: recoveryWrap.iterations,
                    key_check: await computeKeyCheck(raw),
                },
            };
        });
    } catch (error) {
        console.error(error);
        clickErrorMessage('Algo não deu certo', 'Seu navegador não conseguiu gerar a chave. Atualize o navegador e tente novamente.');
        return;
    }

    $('#ek-recovery-key').text(pendingEncryptionKey.recoveryKey);
    goToEncryptionKeyStep(3);
}

function storeEncryptionKey() {
    if (!pendingEncryptionKey || !$('#ek-saved').is(':checked')) return;

    sendRequestByJson('/encryption-key', 'POST', pendingEncryptionKey.payload, async function() {
        await cacheMedicalRecordKey(await importRecordKey(pendingEncryptionKey.raw));
        pendingEncryptionKey = null;

        // Já cria o par das escalas e das dinâmicas, para poder guardar sem login depois.
        await ensureScaleKeyPair().catch((error) => console.log('Error', error));

        appDialog({
            tone: 'lock',
            title: 'Prontuários protegidos',
            text: 'Sua senha mestre foi configurada. A partir de agora o conteúdo dos prontuários é criptografado no seu navegador.',
            confirmText: 'Continuar',
            allowOutsideClick: false,
        }).then(() => redirectTo($('#encryption-key-page').data('redirect') || '/medical-records'));
    });
}

async function updatePassphraseEncryptionKey(form) {
    const passphrase = readNewPassphrase(form, 'new_passphrase');
    if (!passphrase) return;

    let unlocked;
    try {
        unlocked = await withLoading(async () => unlockWithPassphrase(await fetchEncryptionKeyDetails(), $(form).find('[name="current_passphrase"]').val()));
    } catch (error) {
        showFieldError(form, 'current_passphrase', 'Senha mestre atual incorreta.');
        return;
    }

    await sendNewPassphrase(form, unlocked, passphrase, 'Senha mestre alterada.');
}

async function recoverEncryptionKey(form) {
    const passphrase = readNewPassphrase(form, 'new_passphrase');
    if (!passphrase) return;

    let unlocked;
    try {
        unlocked = await withLoading(async () => unlockWithRecoveryKey(await fetchEncryptionKeyDetails(), $(form).find('[name="recovery_key"]').val()));
    } catch (error) {
        showFieldError(form, 'recovery_key', 'Chave de recuperação incorreta.');
        return;
    }

    await sendNewPassphrase(form, unlocked, passphrase, 'Acesso recuperado. Use a nova senha mestre para abrir seus prontuários.');
}

async function sendNewPassphrase(form, unlocked, passphrase, message) {
    const wrap = await withLoading(() => wrapRecordKey(unlocked.raw, passphrase));
    const payload = {
        passphrase_wrapped_key: wrap.wrapped,
        passphrase_salt: wrap.salt,
        passphrase_iterations: wrap.iterations,
        key_check: await computeKeyCheck(unlocked.raw),
    };

    sendRequestByJson('/encryption-key/passphrase', 'PUT', payload, async function() {
        await cacheMedicalRecordKey(unlocked.key);
        form.reset();
        successResponseJson('Tudo certo', message);

        const redirect = $('#encryption-key-page').data('redirect');
        if (redirect) {
            setTimeout(() => redirectTo(redirect), 1500);
        }
    });
}

// Gera uma nova chave de recuperação; a anterior deixa de funcionar.
async function updateRecoveryEncryptionKey(form) {
    let unlocked;
    try {
        unlocked = await withLoading(async () => unlockWithPassphrase(await fetchEncryptionKeyDetails(), $(form).find('[name="current_passphrase"]').val()));
    } catch (error) {
        clearFormError(form);
        showFieldError(form, 'current_passphrase', 'Senha mestre incorreta.');
        return;
    }

    const recoveryKey = generateRecoveryKey();
    const wrap = await withLoading(() => wrapRecordKey(unlocked.raw, normalizeRecoveryKey(recoveryKey)));
    const payload = {
        recovery_wrapped_key: wrap.wrapped,
        recovery_salt: wrap.salt,
        recovery_iterations: wrap.iterations,
        key_check: await computeKeyCheck(unlocked.raw),
    };

    sendRequestByJson('/encryption-key/recovery', 'PUT', payload, function() {
        form.reset();
        clearFormError(form);
        $('#ek-new-recovery-key').text(recoveryKey);
        $('#ek-new-recovery').removeClass('d-none');
    });
}

async function lockMedicalRecords() {
    await clearMedicalRecordKey();
    successResponseJson('Prontuários bloqueados', 'Neste navegador, a senha mestre será pedida novamente para abrir os prontuários.');
}

function downloadRecoveryKit(selector) {
    const key = $(selector).text().trim();
    if (!key) return;

    const page = $('#encryption-key-page');
    const content = [
        'PSI4 - Chave de recuperação dos prontuários',
        '',
        'Conta: ' + page.data('email'),
        'Gerada em: ' + new Date().toLocaleString('pt-BR'),
        '',
        'Chave de recuperação:',
        key,
        '',
        'Use esta chave para criar uma nova senha mestre caso esqueça a atual.',
        'Guarde este arquivo em local seguro (ex.: impresso ou em um gerenciador de senhas).',
        'Sem a senha mestre e sem esta chave, ninguém (nem a equipe do PSI4) consegue abrir seus prontuários.',
    ].join('\r\n');

    const url = URL.createObjectURL(new Blob([content], { type: 'text/plain;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = 'psi4-chave-de-recuperacao.txt';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}
