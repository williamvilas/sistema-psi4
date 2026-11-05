// Rascunho da sessão, gerado por IA. O servidor só entrega o arquivo cifrado: o texto abre aqui, com a senha mestre. O
// rascunho vai para o editor e a transcrição fica ao lado, fora dele. O que ela editou só fica no navegador se a sessão
// expirar ou o envio falhar, cifrado como o rascunho do prontuário. Ao aprovar, só o editor é cifrado como prontuário
// comum: a transcrição não entra.

function paginateSessionTranscriptions(url = '/session-transcriptions/paginate') {
    if (!elementExists('.list-paginate')) {
        return;
    }

    $.ajax({
        url: url,
        type: 'GET',
        dataType: 'json',
        beforeSend: function () {
            loadingAjax();
        },
        success: function (response) {
            $('.list-paginate').html(response.data ? response.data.view : response.view);
        },
        error: function (xhr) {
            errorResponseJson(xhr);
        },
        complete: function () {
            closeLoadingAjax();
        },
    });
}

$(document).on('click', '.list-paginate .pagination a', function (e) {
    e.preventDefault();
    paginateSessionTranscriptions($(this).attr('href'));
});

$(document).on('click', '.js-reject-session-transcription', function () {
    const { uuid, patient, date } = this.dataset;

    rejectSessionTranscription(uuid, patient, date);
});

function sessionTranscriptionDraftKey(uuid) {
    return 'session-transcription-draft-' + uuid;
}

// Pedido só depois do desbloqueio: o tempo diante da senha mestre não pode vencer o link.
async function fetchSessionNoteLink(uuid) {
    const response = await fetch(`/session-transcriptions/${uuid}/note`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (isSessionExpiredResponse(response)) {
        handleSessionExpired();
        const error = new Error('Sessão expirada.');
        error.sessionExpired = true;
        throw error;
    }

    if (!response.ok) {
        throw httpError(`Erro ao carregar o rascunho: ${response.status}`, response.status);
    }

    const json = await response.json();

    return json.link || json.data.link;
}

async function loadSessionNote(uuid) {
    const privateKey = await getScalePrivateKey();
    const response = await fetch(await fetchSessionNoteLink(uuid));

    // Sem o arquivo é falha de acesso: abrir o editor vazio levaria a aprovar um registro em branco.
    if (!response.ok) {
        throw new Error('Não foi possível carregar o rascunho.');
    }

    return decryptSessionNote(new Uint8Array(await response.arrayBuffer()), privateKey, uuid);
}

function showSessionTranscript(note) {
    if (!elementExists('#sessionTranscriptPanel')) {
        return;
    }

    $('#sessionTranscriptBody').html(renderSessionTranscriptHtml(note));
    $('#sessionTranscriptPanel').removeClass('d-none');
}

function initSessionTranscriptionReview(uuid) {
    $('#form-review-session-transcription').on('submit', function (e) {
        e.preventDefault();
        acceptSessionTranscription(uuid);
    });

    MedicalEditor.init({
        autoDraft: false,
        draftKey: sessionTranscriptionDraftKey(uuid),
        loadContent: async function () {
            const note = await loadSessionNote(uuid);

            showSessionTranscript(note);

            return renderSessionNoteHtml(note);
        },
        onLoadError: function (error) {
            // Cancelou a senha mestre: nada muda, e o rascunho continua na lista.
            if (error && error.cancelled) {
                return redirectTo('/session-transcriptions');
            }

            if (error && error.sessionExpired) {
                return;
            }

            console.error(error);
            clickErrorMessage('Algo não deu certo', 'Não foi possível abrir o rascunho. Nada foi apagado: tente novamente mais tarde.').then(function () {
                redirectTo('/session-transcriptions');
            });
        },
    });
}

async function acceptSessionTranscription(uuid) {
    const key = await unlockMedicalRecordKey();

    if (!key) {
        return;
    }

    try {
        loadingAjax();
        const filename = `medical-records/${generateFilename()}`;
        const contentType = 'application/octet-stream';
        const link = await requestUploadUrl(filename, contentType);

        if (!link) {
            return;
        }

        await uploadToS3MedicalRecords(link, await encryptMedicalContent(MedicalEditor.getHTML(), key, filename), contentType);

        $.ajax({
            url: `/session-transcriptions/${uuid}/accept`,
            type: 'POST',
            data: { notes_path: filename },
            dataType: 'json',
            success: function (response) {
                MedicalEditor.markSaved();
                redirectTo(response.link || response.data.link);
            },
            error: function (xhr) {
                closeLoadingAjax();

                if (shouldKeepMedicalDraft(xhr.status)) {
                    MedicalEditor.saveDraftNow();
                }

                errorResponseJson(xhr);
            },
        });
    } catch (error) {
        closeLoadingAjax();
        console.error(error);

        if (shouldKeepMedicalDraft(error.status)) {
            await MedicalEditor.saveDraftNow();
        }

        clickErrorMessage('Algo não deu certo', 'Não foi possível salvar no prontuário. O texto continua guardado: tente novamente.');
    }
}

function rejectSessionTranscription(uuid, patient, date) {
    Swal.fire({
        icon: 'warning',
        title: 'Recusar este rascunho?',
        text: `O rascunho e a transcrição da sessão de ${patient}, de ${date}, serão apagados definitivamente e não entrarão no prontuário.`,
        showCancelButton: true,
        confirmButtonText: 'Recusar e apagar',
        cancelButtonText: 'Voltar',
        reverseButtons: true,
    }).then(function (result) {
        if (!result.isConfirmed) {
            return;
        }

        $.ajax({
            url: `/session-transcriptions/${uuid}`,
            type: 'DELETE',
            dataType: 'json',
            beforeSend: function () {
                loadingAjax();
            },
            success: function (response) {
                // Na lista o editor não existe, e uma edição guardada de antes não pode sobrar.
                if (window.MedicalEditor) {
                    MedicalEditor.markSaved();
                }

                try {
                    sessionStorage.removeItem(sessionTranscriptionDraftKey(uuid));
                } catch (e) {}

                redirectTo(response.link || response.data.link);
            },
            error: function (xhr) {
                closeLoadingAjax();
                errorResponseJson(xhr);
            },
        });
    });
}
