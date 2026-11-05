/**
 * Escala enviada por link (ficha do paciente).
 * O paciente responde sem login; as respostas são cifradas no navegador dele com a chave pública das escalas
 * e só a psicóloga decifra (a chave privada fica embrulhada pela chave dos prontuários).
 */
const formStoreScaleLink = '#form-store-scale-link';
let paginateScaleLinksSeq = 0;

$(document).ready(function () {
    if (!elementExists(formStoreScaleLink)) return;

    initScaleLinks();
});

function initScaleLinks() {
    $('#btn-scale-link').on('click', openScaleLinkModal);

    $(formStoreScaleLink).on('submit', function (e) {
        e.preventDefault();
        storeScaleLink();
    });

    // Trocar a escala esconde o link gerado, para não copiar o link de outra escala por engano.
    $(formStoreScaleLink).on('change', 'input[name="scale"]', () => $('#scale-link-result').addClass('d-none'));

    $('#btn-copy-scale-link').on('click', function () {
        copyScaleLink($('#scale-link-url').val(), this);
    });
    $('#btn-share-scale-link').on('click', shareScaleLink);

    $('#scale-links-list').on('click', '[data-copy-scale-link]', function () {
        copyScaleLink($(this).data('copy-scale-link'));
    });
    $('#scale-links-list').on('click', '[data-revoke-scale-link]', function () {
        revokeScaleLink($(this).data('revoke-scale-link'), $(this).data('scale-name'));
    });
    $('#scale-links-list').on('click', '.js-page-link', function (e) {
        e.preventDefault();
        const url = $(this).attr('href');
        if (url) paginateScaleLinks(null, url);
    });

    if (typeof navigator.share === 'function') {
        $('#btn-share-scale-link').removeClass('d-none');
    }
}

function getScaleLinkModal() {
    return bootstrap.Modal.getOrCreateInstance(document.getElementById('scaleLinkModal'));
}

function openScaleLinkModal() {
    $(formStoreScaleLink)[0].reset();
    $('#scale-link-result').addClass('d-none');
    paginateScaleLinks();
    getScaleLinkModal().show();
}

// url: link de paginação (já traz o paciente); sem url, carrega a primeira página.
function paginateScaleLinks(callback = null, url = null) {
    const seq = ++paginateScaleLinksSeq;
    const list = $('#scale-links-list');

    $.ajax({
        url: url || '/scale-links',
        type: 'GET',
        dataType: 'json',
        data: url ? null : { patient_id: getPatientEvolutionId() },
        beforeSend: function () {
            list.addClass('is-loading');
        },
        success: function (response) {
            if (seq !== paginateScaleLinksSeq) return;

            list.html(response.html);
            if (isFunction(callback)) callback();
        },
        error: function (jqXHR) {
            errorResponseJson(jqXHR, '');
        },
        complete: function () {
            list.removeClass('is-loading');
        }
    });
}

// No primeiro link, gera o par de chaves das escalas (pede a senha mestre se os prontuários estiverem bloqueados).
async function storeScaleLink() {
    const button = $('#btn-store-scale-link');
    const scale = $(formStoreScaleLink).find('input[name="scale"]:checked').val();
    if (!scale) return;

    button.prop('disabled', true);

    let ready = false;
    try {
        ready = await ensureScaleKeyPair();
    } catch (error) {
        console.error(error);
        button.prop('disabled', false);
        return clickErrorMessage('Algo não deu certo', 'Não foi possível preparar a criptografia do link. Por favor, tente novamente.');
    }

    if (!ready) {
        return button.prop('disabled', false);
    }

    sendRequestByJson('/scale-links', 'POST', { patient_id: getPatientEvolutionId(), scale: scale }, function (response) {
        button.prop('disabled', false);
        $('#scale-link-name').text(response.scale);
        $('#scale-link-url').val(response.url);
        $('#scale-link-expires').text(response.expires_at);
        $('#scale-link-result').removeClass('d-none');
        paginateScaleLinks();
    }, function (jqXHR) {
        button.prop('disabled', false);
        errorResponseJson(jqXHR, formStoreScaleLink);
    });
}

function copyScaleLink(url, button = null) {
    if (!url) return;

    copyToClipboard(url, 'Link copiado', function () {
        if (!button) return;

        const $button = $(button);
        clearTimeout($button.data('copiedTimer'));
        $button.addClass('is-copied').find('span').text('Copiado!');
        $button.find('i').attr('class', 'fa-solid fa-check me-1');

        $button.data('copiedTimer', setTimeout(function () {
            $button.removeClass('is-copied').find('span').text('Copiar');
            $button.find('i').attr('class', 'fa-regular fa-copy me-1');
        }, 2000));
    });
}

async function shareScaleLink() {
    const url = $('#scale-link-url').val();
    if (!url) return;

    try {
        await navigator.share({ title: 'Questionário', text: 'Quando puder, responda este questionário:', url: url });
    } catch (e) {
        // Fechar a janela de compartilhamento não é erro; nas demais falhas, copia o link
        if (e.name !== 'AbortError') {
            copyScaleLink(url, $('#btn-copy-scale-link')[0]);
        }
    }
}

function revokeScaleLink(id, name) {
    const safeName = $('<div>').text(name).html();

    confirmAction('Cancelar o link da escala <strong>' + safeName + '</strong>? O paciente não conseguirá mais responder por ele.', function () {
        sendRequestByJson('/scale-links/revoke', 'PATCH', { id: id }, function (response) {
            $('#scale-link-result').addClass('d-none');
            paginateScaleLinks();
            successResponseJson(response.title, response.message);
        });
    }, 'danger', 'Cancelar link', 'Manter');
}
