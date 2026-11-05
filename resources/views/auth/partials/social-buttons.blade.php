@php($showTerms = $showTerms ?? false)

<div class="text-center my-3">
    <span class="text-secondary-emphasis small fw-medium" style="color: #5d7e86;">ou continue com</span>
</div>
<div class="d-flex flex-wrap gap-2 justify-content-center">
    <a href="{{ route('social.redirect', 'google') }}" id="btn-google-{{ $context }}" class="btn btn-social flex-fill d-flex align-items-center justify-content-center">
        <i class="fab fa-google me-2"></i> Google
    </a>
    <a href="{{ route('social.redirect', 'linkedin') }}" id="btn-linkedin-{{ $context }}" class="btn btn-social flex-fill d-flex align-items-center justify-content-center">
        <i class="fab fa-linkedin me-2"></i> LinkedIn
    </a>
</div>

@if ($showTerms)
    <p class="text-center small mt-3 mb-0" style="color: #486e78;">
        Ao continuar com Google ou LinkedIn, você concorda com os
        <a href="{{ route('terms-service') }}" target="_blank" rel="noopener" style="color: #1f7a7a;">Termos de Uso</a>
        e a
        <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener" style="color: #1f7a7a;">Política de Privacidade</a>.
    </p>
@endif

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const buttons = [
            { el: document.getElementById('btn-google-{{ $context }}'), html: '<i class="fab fa-google me-2"></i> Google' },
            { el: document.getElementById('btn-linkedin-{{ $context }}'), html: '<i class="fab fa-linkedin me-2"></i> LinkedIn' },
        ].filter(function (b) { return b.el; });

        buttons.forEach(function (b) {
            b.el.addEventListener('click', function (e) {
                if (b.el.classList.contains('disabled')) {
                    e.preventDefault();
                    return;
                }

                buttons.forEach(function (other) {
                    other.el.classList.add('disabled');
                    other.el.setAttribute('aria-disabled', 'true');
                });
                b.el.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Aguarde...';
            });
        });

        // Restaura os botões ao voltar pelo histórico do navegador (bfcache)
        window.addEventListener('pageshow', function (event) {
            if (!event.persisted) return;
            buttons.forEach(function (b) {
                b.el.classList.remove('disabled');
                b.el.removeAttribute('aria-disabled');
                b.el.innerHTML = b.html;
            });
        });
    });
</script>
