// Aprovação do pedido da sala de vídeo. A senha mestre abre a chave privada só aqui, e a sala nunca a recebe.

$(document).on('click', '#approveVideoRoomAuthorization', function () {
    const { id, publicKey, info } = this.dataset;

    approveVideoRoomAuthorization(id, publicKey, info);
});

async function approveVideoRoomAuthorization(id, serverPublicKey, info) {
    let proof;

    try {
        proof = await proveScalePrivateKey(await getScalePrivateKey(), serverPublicKey, info);
    } catch (error) {
        if (!error.cancelled) {
            console.error(error);
            clickErrorMessage('Algo não deu certo', 'Não foi possível autorizar. Por favor, tente novamente.');
        }

        return;
    }

    sendRequestByJson(`/video-room-authorizations/${id}`, 'POST', { proof: proof }, function () {
        $('#videoRoomAuthorization').addClass('d-none');
        $('#videoRoomAuthorizationDone').removeClass('d-none');
    });
}
