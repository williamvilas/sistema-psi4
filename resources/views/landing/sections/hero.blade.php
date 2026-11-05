{{-- Hero: reconhecimento + bandeira do sigilo. --}}
<section id="hero" class="lp-hero" aria-labelledby="hero-title">
    <div class="lp-container lp-hero-grid">
        <div>
            <span class="lp-eyebrow">Sistema para psicólogas(os)</span>
            <h1 id="hero-title">Nem a nossa equipe de tecnologia consegue ler o que você escreve no prontuário.</h1>
            <p>É um consultório inteiro em um único lugar: Agenda, Cobranças, Receita Saúde, Videochamadas, Escalas e Documentos, seguindo as diretrizes da LGPD e do CFP.</p>
            <div class="lp-actions">
                <a href="{{ $linkRegister }}" class="lp-btn lp-btn-primary lp-btn-lg" data-funnel="landing_cta_register">{{ $ctaRegister }}</a>
            </div>
            <ul class="lp-reassure">
                <li><i class="bi bi-check2" aria-hidden="true"></i> Plano grátis para sempre, com a criptografia completa</li>
                <li><i class="bi bi-check2" aria-hidden="true"></i> Sem cartão e sem fidelidade</li>
            </ul>
            <p class="lp-hero-trust">
                <i class="bi bi-shield-check" aria-hidden="true"></i>
                <span>Pensado a partir das Resoluções CFP 001/2009 e 06/2019: sigilo, guarda de 5 anos e documentos no padrão. <a href="{{ $linkSecurity }}">Veja como</a></span>
            </p>
        </div>

        <figure class="lp-hero-photo">
            <img src="{{ asset('imgs/landing-page/hero-psicologa-tela-1120.webp') }}"
                 srcset="{{ asset('imgs/landing-page/hero-psicologa-tela-560.webp') }} 560w, {{ asset('imgs/landing-page/hero-psicologa-tela-1120.webp') }} 1120w"
                 sizes="(min-width: 992px) 520px, (min-width: 576px) 560px, 100vw"
                 width="1120" height="896" fetchpriority="high" decoding="async"
                 alt="Psicóloga sorrindo, com a mão no rosto, olhando para a tela do notebook em uma sala aconchegante, com uma caneca e uma planta sobre a mesa.">
            <span class="lp-hero-photo-tag"><i aria-hidden="true"></i>Para psicólogas(os)</span>
        </figure>
    </div>
</section>
