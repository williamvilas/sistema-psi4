@extends('layouts.app')
@section('title', 'Cadastrar Prontuários')

@section('header')
    @empty($limitReached)
        <link href="{{ mix('css/medical-editor.css') }}" rel="stylesheet">
    @endempty
@endsection

@section('content')
    @empty($limitReached)
        @include('medical-records._editor')
    @else
        @include('medical-records._limit-reached')
    @endempty
@endsection

@section('footer')
    @empty($limitReached)
        <script src="{{ mix('js/medical-editor.js') }}" defer></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                MedicalEditor.init({ draftKey: 'medical-record-draft', autoDraft: false });
            });
        </script>
    @endempty
@endsection
