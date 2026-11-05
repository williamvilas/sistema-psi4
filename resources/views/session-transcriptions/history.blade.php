@extends('layouts.app')
@section('title', 'Histórico de transcrições')

@section('header')
    @include('components.css-dash')
@endsection

@section('content')
    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            <i class="fas fa-clock-rotate-left me-2" aria-hidden="true"></i> Histórico de transcrições
        </h3>
    </div>

    <p class="text-muted">Aqui fica a prova do que cada paciente decidiu. Ela não traz nada do que foi dito e continua aqui mesmo depois que o texto é apagado.</p>

    <p><a href="{{ route('session-transcriptions.index') }}"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i> Rascunhos da sessão</a></p>

    <div class="list-paginate"></div>
@endsection

@section('footer')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            paginateSessionTranscriptions('/session-transcriptions/history/paginate');
        });
    </script>
@endsection
