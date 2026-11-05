<footer class="lp-footer">
    <div class="lp-container lp-footer-grid">
        <div>
            <x-logo-psi4 :size="26" text="var(--lp-ink)" accent="var(--lp-teal)" />
            <p class="mt-3 mb-1">Sigilo de verdade. E mais tempo para a clínica.</p>
            <p class="mb-3">Pensado a partir das Resoluções CFP 001/2009 e 06/2019.</p>
            <p class="mb-1"><strong>{{ $appName }}</strong> é um produto de <strong>FGW Sistemas</strong></p>
            <p class="mb-3">CNPJ 46.537.966/0001-63 · Lavras/MG</p>
            <p class="mb-0">© {{ date('Y') }} {{ $appName }}. Todos os direitos reservados.</p>
        </div>
        <nav aria-label="Rodapé">
            <ul>
                <li><a href="{{ $linkSecurity }}" data-funnel="landing_cta_security">Segurança</a></li>
                <li><a href="{{ $linkPrivacy }}">Política de Privacidade</a></li>
                <li><a href="{{ $linkTerms }}">Termos de Uso</a></li>
                <li><a href="{{ $linkLogin }}">Entrar</a></li>
            </ul>
        </nav>
    </div>
</footer>
