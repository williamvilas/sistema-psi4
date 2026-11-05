@php($total = $attendance['completed'] + $attendance['no_show'] + $attendance['canceled'])
@php($firstSession = $attendance['first_session_on'])

@if($firstSession)
    <p class="pat-evo-since" data-evolution="since">
        <i class="far fa-calendar-check me-1"></i>
        Em acompanhamento desde {{ $firstSession->format('d/m/Y') }}
        ({{ $firstSession->locale('pt_BR')->diffForHumans(now($timezone), ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2, 'join' => ' e ']) }})
    </p>
@endif

@if($total === 0)
    <div class="dash-empty"><i class="far fa-calendar"></i>Nenhuma sessão registrada neste período.</div>
@else
    <div class="pat-evo-grid">
        <div class="pat-evo-tile" data-evolution="completed">
            <span class="pat-evo-label">Sessões realizadas</span>
            <strong>{{ $attendance['completed'] }}</strong>
        </div>
        <div class="pat-evo-tile" data-evolution="presence">
            <span class="pat-evo-label">Presença</span>
            <strong>{{ is_null($attendance['presence_rate']) ? '-' : formatNumber($attendance['presence_rate']).'%' }}</strong>
        </div>
        <div class="pat-evo-tile" data-evolution="no_show">
            <span class="pat-evo-label">Faltas</span>
            <strong>{{ $attendance['no_show'] }}</strong>
        </div>
        <div class="pat-evo-tile" data-evolution="canceled">
            <span class="pat-evo-label">Cancelamentos</span>
            <strong>{{ $attendance['canceled'] }}</strong>
        </div>
        <div class="pat-evo-tile" data-evolution="interval">
            <span class="pat-evo-label">Intervalo médio</span>
            <strong>{{ is_null($attendance['avg_interval_days']) ? '-' : formatNumber($attendance['avg_interval_days']).' dias' }}</strong>
        </div>
        <div class="pat-evo-tile" data-evolution="last">
            <span class="pat-evo-label">Última sessão</span>
            <strong>{{ $attendance['last_session_on']?->format('d/m/Y') ?? '-' }}</strong>
        </div>
    </div>
@endif
