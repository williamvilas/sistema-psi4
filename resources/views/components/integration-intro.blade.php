{{--
    Hero de apresentação exibido quando uma integração externa ainda não foi conectada.
    Parâmetros: $title, $text, $url, $buttonLabel, $buttonIcon, $logo (url) ou $icon (classe FontAwesome),
    $logoAlt, $note, $gradient (css), $dismissLabel e $dismissAction (JS do botão secundário, opcional).
--}}
@props(['title', 'text', 'url', 'buttonLabel', 'buttonIcon' => 'fas fa-link', 'logo' => null, 'logoAlt' => '', 'icon' => null, 'note' => null, 'gradient' => null, 'dismissLabel' => null, 'dismissAction' => null])

<style>
    .integration-intro .hero { background: var(--intro-gradient, linear-gradient(135deg, #009ee3 0%, #0070c9 100%)); color: #fff; border-radius: 1rem; padding: clamp(1.5rem, 5vw, 3rem) clamp(1rem, 4vw, 1.5rem); text-align: center; }
    .integration-intro .hero-icon { display: flex; align-items: center; justify-content: center; width: clamp(72px, 14vw, 96px); height: clamp(72px, 14vw, 96px); margin: 0 auto clamp(1.25rem, 4vw, 2rem); border-radius: 50%; background: #fff; color: #4285f4; font-size: clamp(2rem, 6vw, 2.75rem); overflow: hidden; }
    .integration-intro .hero-icon img { width: 100%; height: 100%; object-fit: contain; }
    .integration-intro .hero h2 { font-size: clamp(1.35rem, 4vw, 2rem); }
    .integration-intro .hero p { max-width: 640px; margin: 0 auto 1.5rem; opacity: .95; font-size: clamp(1rem, 2.5vw, 1.34rem); }
    .integration-intro .hero .btn { min-height: 44px; width: 100%; }
    .integration-intro .hero-note { display: block; margin-top: 1rem; opacity: .9; }
    .integration-intro .hero-actions { display: flex; flex-direction: column; gap: .75rem; justify-content: center; }
    @media (min-width: 768px) {
        .integration-intro .hero .btn { width: auto; }
        .integration-intro .hero-actions { flex-direction: row; }
    }
</style>

<div class="integration-intro">
    <div class="hero" @if($gradient) style="--intro-gradient: {{ $gradient }}" @endif>
        <div class="hero-icon">
            @if($logo)
                <img src="{{ $logo }}" alt="{{ $logoAlt }}">
            @else
                <i class="{{ $icon }}" aria-hidden="true"></i>
            @endif
        </div>
        <h2 class="fw-bold mb-3">{{ $title }}</h2>
        <p>{{ $text }}</p>

        <div class="hero-actions">
            <a href="{{ $url }}" class="btn btn-light text-black btn-lg fw-bold px-4">
                <i class="{{ $buttonIcon }} me-2" aria-hidden="true"></i>{{ $buttonLabel }}
            </a>

            @if($dismissLabel)
                <button type="button" class="btn btn-outline-light btn-lg px-4" onclick="{{ $dismissAction }}">{{ $dismissLabel }}</button>
            @endif
        </div>

        @if($note)
            <small class="hero-note"><i class="fas fa-shield-halved me-1" aria-hidden="true"></i>{{ $note }}</small>
        @endif
    </div>
</div>
