$(document).ready(function() {
    initProfileForm();
    initPasswordRules();

    $('#form-update-password').submit(function (e) {
        e.preventDefault();
        updatePassword();
    });

    $('#form-force-change-password').submit(function (e) {
        e.preventDefault();
        forceChangePassword();
    });
});

/** Serializa o formulário de perfil sem o token, para comparar com o estado salvo. */
function serializeProfileForm() {
    return $('#form-profile-user').find('input[name="name"], input[name="document"], input[name="email"], input[name="whatsapp"]').serialize();
}

function initProfileForm() {
    const selector = '#form-profile-user';
    if (!elementExists(selector)) return;

    const form = $(selector);
    let savedState = serializeProfileForm();
    let savedValues = {};
    form.find('input.form-control').each(function () { savedValues[this.name] = this.value; });

    const refreshDirty = function () {
        const dirty = serializeProfileForm() !== savedState;
        $('#btn-save-profile').prop('disabled', !dirty);
        $('#btn-reset-profile, #profile-dirty-hint').toggleClass('d-none', !dirty);
        form.data('dirty', dirty);
    };

    form.on('input change', 'input', refreshDirty);

    $('#btn-reset-profile').on('click', function () {
        form.find('input.form-control').each(function () { $(this).val(savedValues[this.name]).removeClass('is-invalid'); });
        clearFormError(selector);
        refreshDirty();
    });

    // Avisa antes de sair da tela com alterações não salvas.
    window.addEventListener('beforeunload', function (e) {
        if (!form.data('dirty')) return;
        e.preventDefault();
        e.returnValue = '';
    });

    form.submit(function (e) {
        e.preventDefault();
        updateProfile(function () {
            savedState = serializeProfileForm();
            form.find('input.form-control').each(function () { savedValues[this.name] = this.value; });
            refreshDirty();
        });
    });
}

function updateProfile(callback) {
    sendRequest(
        '/profile',
        'PUT',
        '#form-profile-user',
        'json',
        function (response) {
            $('.user-name').text(response.data.name);
            $('.user-email').text(response.data.email);
            $('#profile-head-name').text(response.data.full_name).attr('title', response.data.full_name);
            $('#profile-head-email').text(response.data.email);
            callback();
            successResponseJson(response.title, response.message);
        }
    );
}

/** Marca em tempo real os requisitos da nova senha (tamanho mínimo). */
function initPasswordRules() {
    if (!elementExists('#password-rules')) return;

    const check = function () {
        const password = $('#password').val();
        const rules = {
            length: password.length >= 8,
        };

        $.each(rules, function (rule, ok) {
            $('[data-password-rule="' + rule + '"]').toggleClass('is-ok', ok)
                .find('i').toggleClass('fa-solid fa-circle-check', ok).toggleClass('fa-regular fa-circle', !ok);
        });
    };

    $('#password').on('input', check);
}

function updatePassword() {
    sendRequest(
        '/password',
        'PUT',
        '#form-update-password',
        'json',
        function (response) {
            $('#form-update-password')[0].reset();
            $('#password').trigger('input');
            successResponseJson(response.title, response.message);
        }
    );
}

function forceChangePassword() {
    sendRequest(
        '/force-change-password',
        'PUT',
        '#form-force-change-password',
        'json',
        function (response) {
            successResponseJson(response.title, response.message).then(function () {
                redirectTo(response.url);
            });
        }
    );
}
