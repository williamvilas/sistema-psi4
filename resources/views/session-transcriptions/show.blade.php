@extends('layouts.app')
@section('title', 'Rascunho da sessão')

@section('header')
    @empty($limitReached)
        <link href="{{ mix('css/medical-editor.css') }}" rel="stylesheet">
    @endempty
@endsection

@section('content')
    @empty($limitReached)
        @include('medical-records._editor', ['transcription' => $transcription])
    @else
        @include('medical-records._limit-reached', ['backUrl' => route('session-transcriptions.index'), 'backLabel' => 'Voltar aos rascunhos'])
    @endempty
@endsection

@section('footer')
    @empty($limitReached)
        <script src="{{ mix('js/medical-editor.js') }}" defer></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                initSessionTranscriptionReview(@js($transcription->uuid));
            });
        </script>
    @endempty
@endsection
