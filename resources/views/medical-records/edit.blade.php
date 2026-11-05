@extends('layouts.app')
@section('title', 'Editar Prontuário')

@section('header')
    <link href="{{ mix('css/medical-editor.css') }}" rel="stylesheet">
@endsection

@section('content')
    @include('medical-records._editor')
@endsection

@section('footer')
    <script src="{{ mix('js/medical-editor.js') }}" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const initEditor = (contentLink) => MedicalEditor.init({
                contentLink: contentLink,
                notesPath: @js($record->notes_path),
                draftKey: 'medical-record-draft-edit-' + getId(),
                autoDraft: false
            });

            initAccessLogsPanel();

            initEditor(@js($contentLink));
        });
    </script>
@endsection
