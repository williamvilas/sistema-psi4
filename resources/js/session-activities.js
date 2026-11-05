/**
 * Ativação das dinâmicas da sessão de vídeo: cria o par de chaves, se faltar. Só precisa ser feito uma vez. Depois, a
 * psicóloga autoriza cada sessão no PSI4 com a senha mestre e guarda no prontuário pela chamada.
 */
$(document).on('click', '#activateSessionActivities', async function () {
    try {
        if (!await ensureScaleKeyPair()) return;
    } catch (error) {
        console.error(error);
        clickErrorMessage('Algo não deu certo', 'Não foi possível preparar a chave. Por favor, tente novamente.');
        return;
    }

    successResponseJson('Dinâmicas ativadas', 'Agora você pode guardar as dinâmicas no prontuário durante a sessão de vídeo.');
    setTimeout(() => window.location.reload(), 1500);
});
