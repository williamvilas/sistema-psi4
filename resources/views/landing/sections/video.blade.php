@php
    // Mesmo conteúdo do hero do PSI4 Meet. Só afirmações que a stack garante: o vídeo passa pelo servidor (JVB),
    // então não é de ponta a ponta. O link da sala sai 5 minutos antes (routes/console.php).
    $videoBadges = ['Incluída no plano grátis', 'Chamada criptografada', 'Sem cadastro para o paciente'];
    $videoStats = [
        ['1 link', 'para cada sessão, com um código aleatório que não tem o nome do paciente'],
        ['5 min', 'antes da sessão o link chega no WhatsApp ou SMS do paciente, sem você digitar nada'],
        ['Zero', 'instalações ou contas para o paciente entrar na sala'],
    ];
@endphp

<section id="videochamada" class="lp-section lp-video" aria-labelledby="video-title">
    <div class="lp-container">
        <div class="lp-video-grid">
            <div class="lp-reveal">
                <span class="lp-eyebrow">Atendimento online</span>
                <h2 class="mb-5" id="video-title">Videochamadas <span>seguras</span> para psicólogas(os)</h2>
                <p class="lp-lead"><i class="bi bi-check2 lp-check-icon" aria-hidden="true"></i>No PSI4 Meet, seu paciente entra só com o CPF (ou o telefone, se não tiver CPF), sem instalar nada. Só entra quem tem horário marcado, e na sala ficam apenas você e o paciente.</p>
                <p class="lp-lead"><i class="bi bi-check2 lp-check-icon" aria-hidden="true"></i>Faça dinâmicas em tempo real na própria sala, como a Roda das emoções, e o registro vai cifrado para o prontuário.</p>
            </div>

            <figure class="lp-video-art lp-reveal">
                <img src="{{ asset('imgs/landing-page/sala-video-03.webp') }}" width="889" height="422" loading="lazy" decoding="async"
                     alt="Sala de vídeo do {{ $appName }} no notebook e no celular: a psicóloga de um lado e a paciente, com o rosto desfocado, do outro.">
            </figure>
        </div>

        <ul class="lp-video-stats lp-reveal">
            @foreach($videoStats as [$value, $text])
                <li><strong>{{ $value }}</strong><p>{{ $text }}</p></li>
            @endforeach
        </ul>

        {{-- Com a transcrição ligada, o aviso sobre o áudio fica na seção de transcrição --}}
        @unless($transcriptionEnabled)
            <p class="lp-center lp-muted lp-small mt-4 lp-measure">O áudio e o vídeo trafegam mas não ficam guardados no nosso servidor.</p>
        @endunless
    </div>
</section>
