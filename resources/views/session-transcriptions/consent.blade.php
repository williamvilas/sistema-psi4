@extends('layouts.app')
@section('title', 'Consentimento da transcrição')

@section('content')
    @php($timezone = \App\Helpers\UserHelper::getTimezone())

    <div class="page-header d-print-none">
        <h3 class="fw-bold mb-3 title-page-header">
            <i class="fas fa-file-signature me-2" aria-hidden="true"></i> Consentimento da transcrição
        </h3>
    </div>

    <p class="d-print-none">
        <a href="{{ route('session-transcriptions.history') }}"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i> Histórico</a>
        <button type="button" class="btn btn-sm btn-outline-secondary ms-3" onclick="window.print()"><i class="fas fa-print me-1" aria-hidden="true"></i> Imprimir ou salvar em PDF</button>
    </p>

    <div class="card">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-4">Paciente</dt>
                <dd class="col-sm-8">{{ $transcription->patient?->name ?? __('session-transcriptions.patient_removed') }}</dd>
                <dt class="col-sm-4">Data da sessão</dt>
                <dd class="col-sm-8">{{ $transcription->occurred_on->format('d/m/Y') }}</dd>
                <dt class="col-sm-4">Situação da transcrição</dt>
                <dd class="col-sm-8">{{ __('session-transcriptions.status.'.$transcription->status->value) }}</dd>
                @if($transcription->adult_confirmed_at)
                    <dt class="col-sm-4">Maioridade</dt>
                    <dd class="col-sm-8">Confirmada pela psicóloga em {{ $transcription->adult_confirmed_at->timezone($timezone)->format('d/m/Y H:i') }}, porque o cadastro não tinha data de nascimento.</dd>
                @endif
            </dl>
        </div>
    </div>

    @forelse($transcription->consents as $consent)
        <div class="card mt-3">
            <div class="card-body">
                <h4 class="h6">{{ __('session-transcriptions.decision.'.$consent->decision->value) }}</h4>
                <dl class="row mb-2">
                    <dt class="col-sm-4">Data e hora</dt>
                    <dd class="col-sm-8">{{ $consent->decided_at->timezone($timezone)->format('d/m/Y H:i:s') }}</dd>
                    <dt class="col-sm-4">Participante da sala</dt>
                    <dd class="col-sm-8"><code>{{ $consent->participant_ref }}</code></dd>
                    <dt class="col-sm-4">IP</dt>
                    <dd class="col-sm-8">{{ $consent->ip ?: 'Não registrado' }}</dd>
                    <dt class="col-sm-4">Versão do texto</dt>
                    <dd class="col-sm-8">{{ $consent->consent_version }}</dd>
                    <dt class="col-sm-4">Hash SHA-256 do texto</dt>
                    <dd class="col-sm-8 text-break"><code>{{ $consent->consent_hash }}</code></dd>
                </dl>
                <p class="fw-semibold mb-1">Texto apresentado</p>
                <p class="mb-0">{{ $consent->consent_text ?: 'Texto não guardado neste registro.' }}</p>
            </div>
        </div>
    @empty
        <p class="text-muted mt-3">O paciente não chegou a responder a este pedido.</p>
    @endforelse
@endsection
