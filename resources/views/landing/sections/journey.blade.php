@php
    $howVideoUrl = config('landing.how_it_works_video_url');
@endphp

<section id="como-funciona" class="lp-section lp-section-alt" aria-labelledby="journey-title">
    <div class="lp-container">
        <div class="lp-measure lp-center lp-reveal">
            <span class="lp-eyebrow">Como funciona</span>
            <h2 id="journey-title">Cuide dos seus pacientes. A gente cuida do resto.</h2>
            <p class="lp-lead">Antes, durante e depois de cada sessão, a parte operacional fica mais leve.</p>
        </div>

        <div class="lp-how-video lp-reveal">
            <div class="lp-how-frame">
                @if($howVideoUrl)
                    <iframe src="{{ $howVideoUrl }}" title="Veja como o {{ $appName }} funciona" loading="lazy"
                            allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                            allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                @else
                    <div class="lp-how-empty">
                        <span class="lp-how-play"><i class="bi bi-play-fill" aria-hidden="true"></i></span>
                        <p>Em breve: veja o {{ $appName }} em poucos minutos</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
