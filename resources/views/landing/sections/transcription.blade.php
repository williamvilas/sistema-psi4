@php
    // Só afirmações que o fluxo garante (SessionTranscriptionService): sem o aceite do paciente não há gravação,
    // o rascunho chega cifrado com a chave da psicóloga e só o texto aprovado entra no prontuário.
    // Demonstração em vídeo curto (MP4 sem som, em loop): mais leve e nítido que GIF. Enquanto o arquivo não existe, fica o espaço reservado.
    $transcriptionDemo = 'imgs/landing-page/transcricao-sessao.mp4';
    $transcriptionDemoExists = file_exists(public_path($transcriptionDemo));
@endphp

<section id="transcricao" class="lp-section lp-section-alt lp-transcription" aria-labelledby="transcription-title">
    <div class="lp-container">
        <div class="lp-video-grid">
            <div class="lp-reveal">
                <span class="lp-eyebrow">Transcrição com IA</span>
                <h2 class="mb-5" id="transcription-title">Da sessão ao rascunho do registro, <span>só com o aceite do paciente</span></h2>
                <p class="lp-lead"><i class="bi bi-check2 lp-check-icon" aria-hidden="true"></i>Você ativa a transcrição na sala, e o paciente decide na própria chamada se aceita. Sem o aceite dele, nada é gravado, e ele pode recusar ou parar quando quiser, sem prejuízo ao atendimento.</p>
                <p class="lp-lead"><i class="bi bi-check2 lp-check-icon" aria-hidden="true"></i>Depois da sessão, chega um rascunho do registro cifrado com a sua chave. Você revisa, ajusta e aprova: só o texto que você aprovar entra no prontuário.</p>
            </div>

            <div class="lp-transcription-media lp-reveal">
                <div class="lp-how-frame">
                    @if($transcriptionDemoExists)
                        <video src="{{ asset($transcriptionDemo) }}" autoplay muted loop playsinline preload="metadata"
                               aria-label="Demonstração: o paciente aceita a transcrição na chamada e a psicóloga revisa o rascunho do registro."></video>
                    @else
                        <div class="lp-how-empty">
                            <span class="lp-how-play"><i class="bi bi-play-fill" aria-hidden="true"></i></span>
                            <p>Em breve: veja a transcrição funcionando</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <p class="lp-center lp-muted lp-small mt-4 lp-measure">
            O vídeo trafega mas não fica guardado no nosso servidor. O áudio só é guardado, por no máximo 24 horas, quando o paciente aceita a transcrição. Ele é processado pela AWS, que pode fazer isso fora do Brasil, e a transcrição não vale para menores de 18 anos.
        </p>
    </div>
</section>
