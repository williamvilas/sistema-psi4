@extends('layouts.app')
@section('title', 'Dinâmicas na sessão de vídeo')

@section('header')
    @include('components.css-dash')
@endsection

@section('content')
    @php
        $activated = $status['has_key_pair'];
    @endphp
    <div class="dash-card p-4 mb-3">
        <h4 class="mb-2">Dinâmicas na sessão de vídeo</h4>
        <p class="mb-2">{{ $disclaimer }}</p>
        <p class="text-muted mb-0">Durante a sessão, abra o botão Dinâmicas na barra da chamada. Ao terminar, você revisa o resultado e guarda no prontuário, no celular ou no computador.</p>
    </div>

    <div class="dash-card p-4 mb-3">
        <h5 class="mb-2">
            Ativação
            <span class="dash-chip {{ $activated ? 'dash-chip-success' : 'dash-chip-muted' }}">{{ $activated ? 'Ativada' : 'Não ativada' }}</span>
        </h5>

        <p class="text-muted">
            Na chamada, basta autorizar a sessão no PSI4 com a sua senha mestre. Ela é digitada só aqui, nunca na sala de vídeo.
        </p>

        @unless ($activated)
            <button type="button" class="btn btn-primary" id="activateSessionActivities">Ativar dinâmicas</button>
            <p class="text-muted small mt-3 mb-0">Pediremos a sua senha mestre, se estiver bloqueada, para preparar a chave que cifra os registros.</p>
        @endunless
    </div>

    <div class="dash-card p-4">
        <h5 class="mb-3">Biblioteca</h5>
        @foreach ($activities as $activity)
            <div class="mb-3">
                <strong>{{ $activity['title'] }}</strong>
                <p class="text-muted mb-0">{{ $activity['description'] }}</p>
            </div>
        @endforeach
    </div>
@endsection
