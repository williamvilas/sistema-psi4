<header class="lp-nav">
    <nav class="lp-container" aria-label="Principal">
        <div class="lp-nav-inner">
            <a href="#hero" class="lp-brand" aria-label="{{ $appName }}, início">
                <x-logo-psi4 :size="30" text="var(--lp-ink)" accent="var(--lp-teal)" />
            </a>
            <ul class="lp-nav-links">
                @foreach($navLinks as $href => $label)
                    <li><a href="{{ $href }}">{{ $label }}</a></li>
                @endforeach
            </ul>
            <div class="lp-nav-actions">
                <a href="{{ $linkLogin }}" class="lp-nav-login">Entrar</a>
                <a href="{{ $linkRegister }}" class="lp-btn lp-btn-primary" data-funnel="landing_cta_register">{{ $ctaRegister }}</a>
                <button type="button" class="lp-menu-toggle" data-bs-toggle="collapse" data-bs-target="#mobile-menu"
                        aria-controls="mobile-menu" aria-expanded="false" aria-label="Abrir menu">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <div id="mobile-menu" class="collapse lp-mobile-menu">
            <ul>
                @foreach($navLinks as $href => $label)
                    <li><a href="{{ $href }}">{{ $label }}</a></li>
                @endforeach
                <li><a href="{{ $linkLogin }}">Entrar</a></li>
            </ul>
        </div>
    </nav>
</header>
