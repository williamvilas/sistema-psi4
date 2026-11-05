@extends('layouts.app')
@section('title', 'Detalhes do Prontuário')

@section('header')
    <link href="{{ mix('css/medical-doc.css') }}" rel="stylesheet">
@endsection

@section('content')

    @if(!empty($record))
        @php($id = encrypt($record->id))
        @php($patient = $record->patient)

        <div id="response-process"></div>

        <div class="doc-view">
            <div class="doc-topbar">
                <a href="{{ route('medical-records.index') }}" class="doc-back" title="Voltar" aria-label="Voltar">
                    <i class="fas fa-arrow-left"></i>
                </a>

                <div class="doc-title-wrap">
                    <h1 class="doc-view-title" title="{{ $record->title }}">{{ $record->title }}</h1>
                    <div class="doc-view-subtitle">{{ $patient->name }} · {{ formatDateByAgenda($record->date_of_consultation) }}</div>
                </div>

                <div class="doc-topbar-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="docAccessToggle" aria-controls="docAccessPanel" aria-expanded="false" title="Quem acessou este prontuário" aria-label="Acessos">
                        <i class="fas fa-clock-rotate-left"></i><span class="d-none d-md-inline ms-1">Acessos</span>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="docPrint" title="Imprimir (Ctrl+P)" aria-label="Imprimir">
                        <i class="fas fa-print"></i><span class="d-none d-md-inline ms-1">Imprimir</span>
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" title="Apagar" aria-label="Apagar"
                            onclick="deleteMedicalRecords(@js($id), @js($patient->name.' - '.$record->title), true, @js($record->isWithinRetention()))">
                        <i class="fas fa-trash"></i><span class="d-none d-md-inline ms-1">Apagar</span>
                    </button>
                    <a href="{{ route('medical-records.edit', ['id' => $id]) }}" class="btn btn-primary btn-sm" id="docEdit" title="Editar (Ctrl+E)" aria-label="Editar">
                        <i class="fas fa-pen"></i><span class="d-none d-md-inline ms-1">Editar</span>
                    </a>
                </div>
            </div>

            <div class="doc-meta">
                <div class="doc-meta-item doc-meta-patient">
                    <label>Paciente</label>
                    <span class="doc-meta-value">{{ $patient->name }}</span>
                </div>

                <div class="doc-meta-item">
                    <label>Data da sessão</label>
                    <span class="doc-meta-value">{{ formatDateByAgenda($record->date_of_consultation) }}</span>
                </div>
            </div>

            <div class="doc-canvas">
                <div id="docLoading" class="doc-no-print">
                    @include('components.icon-loading', ['css' => 'doc-loading'])
                </div>

                <article id="post" class="doc-page doc-page-readonly d-none" aria-label="Conteúdo do prontuário">
                    <div class="doc-print-header">
                        <strong>{{ $patient->name }}</strong> · {{ $record->title }} · {{ formatDateByAgenda($record->date_of_consultation) }}
                    </div>
                    <div id="postContent"></div>
                </article>

                <div id="docError" class="doc-no-print text-center d-none py-5">
                    <p class="mb-3">Não foi possível carregar o conteúdo do prontuário.</p>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="docRetry">Tentar novamente</button>
                </div>
            </div>
        </div>

        @include('access-logs._panel', ['medicalRecordId' => $id])
    @else
        <div id="message-unauthorized" class="mt-5">
            @include('components.unauthorized')
            <p class="text-center sub-alert-p">{{ __('errors.unauthorized') }}</p>
        </div>
    @endif

@endsection

@section('footer')
    @if(!empty($record))
        @php($id = encrypt($record->id))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                initMedicalRecordShow(@js($contentLink ?? null), @js($record->notes_path));
                initAccessLogsPanel();
            });
        </script>
    @endif
@endsection
