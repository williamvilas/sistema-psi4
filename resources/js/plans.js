function checkoutPlan(plan, cycle = 'monthly', paymentMethod = null) {
    sendRequestByJson(
        '/plans/checkout',
        'POST',
        { plan: plan, cycle: cycle, payment_method: paymentMethod },
        function (response) {
            loadingAjax();
            window.location.href = response.link;
        }
    );
}

function continueCheckout(plan) {
    const selected = document.querySelector('#form-checkout-plan .option-input:checked');

    if (!selected) {
        return;
    }

    checkoutPlan(plan, selected.dataset.cycle, selected.dataset.paymentMethod || null);
}

function cancelPlanRenewal(expirationDate) {
    confirmAction(
        'Deseja cancelar a renovação do seu plano? Você continua com acesso até <strong>' + expirationDate + '</strong> e depois volta para o plano Grátis.',
        function () {
            sendRequestByJson('/plans/cancel-renewal', 'POST', {}, function (response) {
                successResponseJson(response.title, response.message).then(function () {
                    window.location.reload();
                });
            });
        }
    );
}

// Links que saem da tela de planos (assinar/renovar/continuar pagamento): evita duplo clique e mostra que está carregando
function bindPlanLinksLoading() {
    document.querySelectorAll('[data-plan-loading]').forEach(function (link) {
        const originalHtml = link.innerHTML;

        link.addEventListener('click', function (e) {
            // Ctrl/Cmd+clique abre em nova aba: a tela atual continua utilizável
            if (e.ctrlKey || e.metaKey || e.shiftKey) return;

            if (link.classList.contains('disabled')) {
                e.preventDefault();
                return;
            }

            link.classList.add('disabled');
            link.setAttribute('aria-disabled', 'true');
            link.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Aguarde...';
        });

        // Restaura o link ao voltar pelo histórico do navegador (bfcache)
        window.addEventListener('pageshow', function (event) {
            if (!event.persisted) return;
            link.classList.remove('disabled');
            link.removeAttribute('aria-disabled');
            link.innerHTML = originalHtml;
        });
    });
}
