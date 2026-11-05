$(document).ready(function() {
    $('#form-store-patients').submit(function (e) {
        e.preventDefault();
        storePatients();
    });

    $('#btn-store-patients-and-new').on('click', function () {
        storePatients(true);
    });

    $('#form-update-patients').submit(function (e) {
        e.preventDefault();
        updatePatients();
    });

    $(document).on('change', '[data-toggle-payer]', function () {
        const $fields = $(this).closest('form').find('[data-payer-field]');
        $fields.prop('hidden', !this.checked);
    });
});

let patientFormSnapshot = null;

function initPatientForm(selector) {
    if (!elementExists(selector)) {
        return;
    }

    // a foto inicial é tirada depois das máscaras do default.js, para não acusar alteração indevida
    $(function () {
        snapshotPatientForm(selector);
    });

    window.addEventListener('beforeunload', function (e) {
        if (!isPatientFormDirty(selector)) {
            return;
        }
        e.preventDefault();
        e.returnValue = '';
    });
}

function snapshotPatientForm(selector) {
    patientFormSnapshot = $(selector).serialize();
}

function isPatientFormDirty(selector) {
    return patientFormSnapshot !== null && $(selector).serialize() !== patientFormSnapshot;
}

function togglePatientFormSaving(selector, saving) {
    $(selector).find('.pat-form-actions .btn').toggleClass('disabled', saving).prop('disabled', saving);
}

function submitPatientForm(selector, url, type, callbackSuccess) {
    clearFormError(selector);

    if (!validForm(selector)) {
        $(selector).find('.is-invalid').first().trigger('focus');
        return;
    }

    togglePatientFormSaving(selector, true);

    sendRequest(
        url,
        type,
        selector,
        'json',
        callbackSuccess,
        true,
        function (jqXHR) {
            togglePatientFormSaving(selector, false);
            errorResponseJson(jqXHR, selector);
            $(selector).find('.is-invalid').first().trigger('focus');
        }
    );
}

function storePatients(andNew = false) {
    const form = '#form-store-patients';

    submitPatientForm(form, '/patients', 'POST', function (response) {
        if (!andNew) {
            patientFormSnapshot = null;
            return redirectTo(response.link);
        }

        const name = $('<div>').text($(form + ' #name').val()).html();

        $(form)[0].reset();
        snapshotPatientForm(form);
        togglePatientFormSaving(form, false);

        showToast('success', 'Paciente cadastrado', {
            html: name,
            didOpen: function () {
                $(form + ' #name').trigger('focus');
            },
        });
    });
}

function updatePatients() {
    submitPatientForm('#form-update-patients', '/patients/' + getId(), 'PUT', function (response) {
        patientFormSnapshot = null;
        return redirectTo(response.link);
    });
}

let paginatePatientsSeq = 0;
let searchPatientsTimer = null;

function paginatePatients(callback = null, loading = true) {
    const form = '#form-search';
    const seq = ++paginatePatientsSeq;
    clearTimeout(searchPatientsTimer);
    totalFilters('#filterModal');
    syncPatientsChips();
    $('#filterModal').modal('hide');

    sendRequest(
        '/patients/paginate',
        'GET',
        form,
        'json',
        function(response) {
            // ignora respostas de buscas antigas que chegaram depois da mais recente
            if (seq !== paginatePatientsSeq) {
                return;
            }
            $('.list-paginate').html(response.html);
            if (isFunction(callback)) {
                callback();
            }
        },
        loading,
        function(jqXHR) {
            errorResponseJson(jqXHR, form);
            setTimeout(function() {
                $('#filterModal').modal('show');
            }, 300);
        }
    );
}

function deletePatients(id, name, redirect = false) {
    const safeName = $('<div>').text(name).html();

    confirmAction('Tem certeza que deseja <strong>apagar</strong> os dados do paciente <strong>' + safeName + '</strong>?', function() {
        sendRequestByJson(
            '/patients',
            'DELETE',
            { ids: [id] },
            function(response) {

                if (redirect) {
                    return redirectTo(response.link);
                }

                paginatePatients(function() {
                    successResponseJson(response.title, response.message);
                });
            }
        );
    }, 'danger', 'Apagar', 'Cancelar');

}

function clearFiltersPatients() {
    clearForm();
    paginatePatients();
}

function initPatientsSearch() {
    const selector = '#patients-search';

    if (!elementExists(selector)) {
        return;
    }

    // busca sem o loading em tela cheia, para não tirar o foco do campo enquanto digita
    $(selector).on('input', function () {
        clearTimeout(searchPatientsTimer);
        searchPatientsTimer = setTimeout(function () {
            paginatePatients(null, false);
        }, 400);
    });
}

function getBirthdayDayRange() {
    return moment().format('DD/MM/YYYY') + ' - ' + moment().format('DD/MM/YYYY');
}

function getBirthdayMonthRange() {
    return moment().startOf('month').format('DD/MM/YYYY') + ' - ' + moment().endOf('month').format('DD/MM/YYYY');
}

function filterPatientsBirthdayDay() {
    $('#birthday_celebrants').val(getBirthdayDayRange());
    paginatePatients();
}

function filterPatientsBirthdayMonth() {
    $('#birthday_celebrants').val(getBirthdayMonthRange());
    paginatePatients();
}

function syncPatientsChips() {
    if (!elementExists('.dash-filter-chip')) {
        return;
    }

    const q = $.trim($('#patients-search').val() || '');
    const birthday = $('#birthday_celebrants').val() || '';
    let active = null;

    if (!q && !birthday) {
        active = 'all';
    } else if (!q && birthday === getBirthdayDayRange()) {
        active = 'birthday-day';
    } else if (!q && birthday === getBirthdayMonthRange()) {
        active = 'birthday-month';
    }

    $('.dash-filter-chip').removeClass('active');
    if (active) {
        $('.dash-filter-chip[data-chip="' + active + '"]').addClass('active');
    }
}

function createConfettiInPatient() {
    const confettiContainer = document.querySelector('.confetti');

    if (!confettiContainer) {
        return;
    }

    for (let i = 0; i < 50; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'confetti-piece';
        confetti.style.left = Math.random() * 100 + '%';
        confetti.style.animationDuration = Math.random() * 3 + 2 + 's';
        confetti.style.background = `hsl(${Math.random() * 360}, 100%, 50%)`;
        confetti.style.opacity = Math.random();
        confetti.style.transform = `rotate(${Math.random() * 360}deg)`;

        confettiContainer.appendChild(confetti);

        setTimeout(() => {
            confetti.remove();
        }, 5000);
    }
}

