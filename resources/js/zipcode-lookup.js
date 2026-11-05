/**
 * Ao terminar de digitar o CEP (8 dígitos) busca o endereço em /zipcode/{cep}
 * e preenche os campos; ao apagar o CEP, limpa todos os campos de endereço.
 * O botão (lupa) permite fazer a mesma busca manualmente. No onboarding a busca
 * usa a rota própria (`url`), já que /zipcode exige o onboarding concluído.
 */
function bindZipcodeLookup(selectorForm, fields, url = '/zipcode/') {
    if (!elementExists(selectorForm) || !elementExists(fields.zipcode)) return;

    const $form = $(selectorForm);
    let lastZipcode = '';

    const clearAddress = function () {
        $form.find([fields.city, fields.neighborhood, fields.street, fields.complement, fields.number].join(',')).val('');
    };

    $form.find(fields.zipcode).on('input', function () {
        const zipcode = $(this).val().replace(/\D/g, '');

        if (zipcode.length === 0) {
            lastZipcode = '';
            clearAddress();
            return;
        }

        if (zipcode.length < 8) {
            lastZipcode = '';
            return;
        }

        if (zipcode === lastZipcode) return;
        lastZipcode = zipcode;

        fillAddress(zipcode);
    });

    if (fields.button && elementExists(fields.button)) {
        $form.find(fields.button).on('click', function () {
            const zipcode = $form.find(fields.zipcode).val().replace(/\D/g, '');

            if (zipcode.length !== 8) {
                clickWarningMessage('CEP incompleto', 'Informe um CEP válido, com 8 dígitos.');
                return;
            }

            lastZipcode = zipcode;
            fillAddress(zipcode);
        });
    }

    function fillAddress(zipcode) {
        // Troca a lupa por um loading enquanto a busca está em andamento
        const $icon = fields.button ? $form.find(fields.button).find('i') : $();
        $icon.removeClass('fa-search').addClass('fa-spinner fa-spin');

        searchZipcode(zipcode, function (address) {
            // Ignora resposta atrasada se o CEP mudou durante a requisição
            if ($form.find(fields.zipcode).val().replace(/\D/g, '') !== zipcode) return;

            $form.find(fields.city).val(address.city);
            $form.find(fields.neighborhood).val(address.neighborhood);
            $form.find(fields.street).val(address.street);
            // Complemento e número são informados pelo usuário: sempre limpos a cada busca
            $form.find(fields.complement).val('');
            $form.find(fields.number).val('').trigger('focus');
        }, function () {
            $icon.removeClass('fa-spinner fa-spin').addClass('fa-search');
        }, url);
    }
}

function searchZipcode(zipcode, callbackSuccess, callbackComplete = null, url = '/zipcode/') {
    $.ajax({
        url: url + zipcode,
        type: 'GET',
        dataType: 'json',
        success: callbackSuccess,
        complete: callbackComplete,
        error: function (jqXHR) {
            if (isSessionExpired(jqXHR)) return;

            const message = jqXHR.responseJSON && jqXHR.responseJSON.message
                ? jqXHR.responseJSON.message
                : 'Não foi possível buscar o CEP. Tente novamente.';

            clickWarningMessage('CEP não encontrado', message);
        },
    });
}
