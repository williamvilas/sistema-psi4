{{-- Mesma tela para link inexistente, expirado, respondido ou cancelado: não revela se o token existe. --}}
@extends('public-scale.layout')
@section('title', 'Link indisponível')
@section('content')
<section class="ps-card ps-center">
    <div class="ps-icon ps-icon-muted" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16Zm-1 4v5h2V8h-2Zm0 7v2h2v-2h-2Z"/></svg>
    </div>
    <h1 class="ps-title">Este link não está mais disponível</h1>
    <p class="ps-text">
        Ele pode já ter sido respondido, ter expirado ou ter sido cancelado.
        Se você ainda precisa responder, peça um novo link a quem enviou para você.
    </p>
</section>
@endsection
