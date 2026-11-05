@extends('layouts.app')
@section('title', 'Rascunhos da sessão')

@section('header')
    @include('components.css-dash')
@endsection

@section('content')
    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            <i class="fas fa-microphone-lines me-2" aria-hidden="true"></i> Rascunhos da sessão
        </h3>
    </div>

    <p class="text-muted">Cada rascunho é gerado por IA a partir da transcrição da sessão e só entra no prontuário se você aprovar, com as suas edições. Até lá, ele fica cifrado, e só você consegue ler. A transcrição e o rascunho nunca entram no prontuário, e o rascunho recusado ou não revisado no prazo é apagado definitivamente.</p>

    @if($usage['limit'] !== 0)
        <p class="small">
            @if($usage['limit'] < 0)
                {{ __('session-transcriptions.usage_unlimited', ['used' => $usage['used']]) }}
            @else
                {{ __('session-transcriptions.usage', ['used' => $usage['used'], 'limit' => $usage['limit']]) }}
            @endif
        </p>
    @endif

    <p><a href="{{ route('session-transcriptions.history') }}"><i class="fas fa-file-signature me-1" aria-hidden="true"></i> Histórico e prova de consentimento</a></p>

    <div class="list-paginate"></div>
@endsection

@section('footer')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            paginateSessionTranscriptions();
        });
    </script>
@endsection
