@extends('public-scale.layout')
@section('title', 'Questionário')
@section('content')
@php($minutes = max(2, (int) ceil(count($scale['items']) / 4)))
<div id="public-scale" data-token="{{ $token }}">

    {{-- Boas-vindas --}}
    <section class="ps-card ps-step is-active" data-step="intro" aria-labelledby="ps-intro-title">
        <div class="ps-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 21s-7.5-4.6-9.6-9.3C.9 8.3 3 4.5 6.7 4.5c2.1 0 3.6 1.2 4.3 2.4.7-1.2 2.2-2.4 4.3-2.4 3.7 0 5.8 3.8 4.3 7.2C19.5 16.4 12 21 12 21Z"/></svg>
        </div>
        <h1 class="ps-title" id="ps-intro-title">Olá! Que bom ter você aqui.</h1>
        <p class="ps-text">
            Este questionário foi enviado por quem acompanha você. Ele ajuda a entender como você tem se sentido
            e a acompanhar sua evolução ao longo do tempo.
        </p>
        <ul class="ps-facts">
            <li><strong>{{ count($scale['items']) }}</strong> perguntas</li>
            <li>cerca de <strong>{{ $minutes }}</strong> minutos</li>
            <li>sem login</li>
        </ul>
        <p class="ps-text ps-text-soft">
            Não existem respostas certas ou erradas. Responda com calma, pensando em como você realmente tem se sentido.
        </p>
        <div class="ps-privacy">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 9V7A5 5 0 0 0 7 7v2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2ZM9 7a3 3 0 0 1 6 0v2H9V7Zm8 12H7v-8h10v8Zm-5-2a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/></svg>
            <span>Suas respostas são cifradas neste aparelho antes de serem enviadas. Só quem acompanha você consegue lê-las, nem mesmo a nossa equipe tem acesso.</span>
        </div>
        <button type="button" class="ps-btn ps-btn-primary ps-btn-block" data-action="start">Começar</button>
    </section>

    {{-- Evento de referência (PCL-5), opcional --}}
    @if($scale['reference_event'])
        <section class="ps-card ps-step" data-step="reference" aria-labelledby="ps-reference-title">
            <h2 class="ps-title ps-title-sm" id="ps-reference-title">Antes de começar</h2>
            <label for="ps-reference-event" class="ps-text">
                Se quiser, descreva em poucas palavras a situação difícil que você vai ter em mente ao responder.
                Este campo é opcional.
            </label>
            <textarea id="ps-reference-event" class="ps-textarea" rows="3" maxlength="500" placeholder="Opcional"></textarea>
            <div class="ps-nav">
                <button type="button" class="ps-btn ps-btn-ghost" data-action="back">Voltar</button>
                <button type="button" class="ps-btn ps-btn-primary" data-action="next">Continuar</button>
            </div>
        </section>
    @endif

    {{-- Perguntas: uma por tela --}}
    <section class="ps-card ps-step ps-question" data-step="question" aria-live="polite">
        <div class="ps-progress-head">
            <span id="ps-counter"></span>
            <span id="ps-scale-name">{{ $scale['name'] }}</span>
        </div>
        <div class="ps-progress" role="progressbar" aria-label="Perguntas respondidas" aria-valuemin="0" aria-valuemax="{{ count($scale['items']) }}" aria-valuenow="0" id="ps-progress">
            <div class="ps-progress-bar" id="ps-progress-bar"></div>
        </div>
        <p class="ps-instruction">{{ $scale['instruction'] }}</p>
        <fieldset class="ps-fieldset">
            <legend class="ps-item" id="ps-item"></legend>
            <div class="ps-options" id="ps-options" role="radiogroup" aria-labelledby="ps-item"></div>
        </fieldset>
        <div class="ps-nav">
            <button type="button" class="ps-btn ps-btn-ghost" data-action="back">Voltar</button>
            <button type="button" class="ps-btn ps-btn-primary d-none" data-action="next" id="ps-next">Próxima</button>
        </div>
    </section>

    {{-- Revisão --}}
    <section class="ps-card ps-step" data-step="review" aria-labelledby="ps-review-title">
        <h2 class="ps-title ps-title-sm" id="ps-review-title">Tudo pronto?</h2>
        <p class="ps-text">Confira suas respostas. Toque em uma delas se quiser mudar.</p>
        <ol class="ps-review" id="ps-review"></ol>
        <div class="ps-nav">
            <button type="button" class="ps-btn ps-btn-ghost" data-action="back">Voltar</button>
            <button type="button" class="ps-btn ps-btn-primary" data-action="submit" id="ps-submit">Enviar respostas</button>
        </div>
    </section>

    {{-- Enviando --}}
    <section class="ps-card ps-step ps-center" data-step="sending" aria-live="assertive">
        <div class="ps-spinner" aria-hidden="true"></div>
        <h2 class="ps-title ps-title-sm">Protegendo e enviando suas respostas…</h2>
        <p class="ps-text ps-text-soft">Isso leva só alguns segundos. Não feche esta página.</p>
    </section>

    {{-- Concluído --}}
    <section class="ps-card ps-step ps-center" data-step="done" aria-labelledby="ps-done-title">
        <div class="ps-icon ps-icon-success" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2Z"/></svg>
        </div>
        <h2 class="ps-title" id="ps-done-title">Respostas enviadas</h2>
        <p class="ps-text">
            Agradecemos por você dedicar esse tempo a si. Suas respostas chegaram com segurança e serão lidas por quem acompanha você.
            Já pode fechar esta página.
        </p>
        <div class="ps-support">
            <strong>Se você estiver passando por um momento difícil</strong>
            <span>Não precisa esperar a próxima sessão. O CVV atende 24 horas, de graça, pelo telefone
                <a href="tel:188">188</a> ou pelo site <a href="https://cvv.org.br" target="_blank" rel="noopener noreferrer">cvv.org.br</a>.</span>
        </div>
    </section>

    {{-- Erro --}}
    <section class="ps-card ps-step ps-center" data-step="error" aria-live="assertive">
        <div class="ps-icon ps-icon-muted" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21Zm12-3h-2v-2h2v2Zm0-4h-2v-4h2v4Z"/></svg>
        </div>
        <h2 class="ps-title ps-title-sm">Não conseguimos enviar</h2>
        <p class="ps-text" id="ps-error-message">Verifique sua conexão com a internet e tente novamente. Suas respostas continuam aqui.</p>
        <button type="button" class="ps-btn ps-btn-primary ps-btn-block" data-action="retry" id="ps-retry">Tentar novamente</button>
    </section>
</div>

<script type="application/json" id="ps-scale">@json($scale)</script>
<script type="application/json" id="ps-public-key">@json($publicKey)</script>
@endsection
@section('footer')
<script src="{{ mix('js/public-scale.js') }}"></script>
@endsection
