{{-- Painel de evolução: frequência (agenda) + escalas cifradas de ponta a ponta, decifradas no navegador. --}}
<div class="dash-card pat-show-card pat-evo" id="patient-evolution" data-patient-id="{{ $id }}">
    <div class="pat-evo-head">
        <h5 class="dash-title pat-show-section-title mb-0"><i class="fas fa-chart-line"></i> Evolução</h5>
        <div class="pat-evo-actions">
            <select class="form-select-46px form-select-sm" id="patient-evolution-period" aria-label="Período">
                @foreach($periods as $months)
                    <option value="{{ $months }}" @selected($months === 6)>{{ $months ? 'Últimos '.$months.' meses' : 'Todo o acompanhamento' }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-outline-primary" id="btn-scale-link">
                <i class="fas fa-paper-plane me-1"></i> Enviar link
            </button>
        </div>
    </div>
    <p class="pat-evo-hint">Frequência pela agenda e resultados das escalas aplicadas. É um apoio à sua leitura clínica, não um diagnóstico.</p>

    <div id="patient-evolution-indicators" class="pat-evo-indicators" aria-live="polite"></div>

    <div class="pat-evo-scales">
        <h6 class="pat-evo-subtitle">Escalas <i class="fas fa-lock ms-1" title="Respostas e pontuação cifradas de ponta a ponta"></i></h6>

        <div id="patient-scales-locked" class="pat-evo-locked">
            <p class="mb-2">As respostas e a pontuação das escalas são cifradas no seu navegador. Nem nós conseguimos ler esses resultados.</p>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-unlock-scales">
                <i class="fas fa-lock-open me-1"></i> Ver resultados
            </button>
        </div>

        <div id="patient-scales-content" class="d-none">
            <div id="patient-scales-empty" class="dash-empty d-none">
                <i class="fas fa-clipboard-list"></i>Nenhuma escala aplicada no período. Aplique uma escala para acompanhar a evolução ao longo do tempo.
            </div>
            <div id="patient-scales-tabs" class="dash-filter-chips mb-2" role="group" aria-label="Escala exibida no gráfico"></div>
            <div id="patient-scales-chart" class="pat-evo-chart"></div>
            <div id="patient-scales-list" class="pat-evo-list"></div>
            <div id="patient-thermometer" class="d-none mt-3">
                <h6 class="pat-evo-subtitle">Termômetro emocional</h6>
                <p class="text-muted small mb-1">Percepção informada pelo paciente nas sessões de vídeo. É um recurso de apoio à conversa, não uma medida.</p>
                <div id="patient-thermometer-chart" class="pat-evo-chart"></div>
            </div>
        </div>
    </div>
</div>

@include('patients.partials.scale-modal', ['scales' => $scales])
@include('patients.partials.scale-link-modal', ['scales' => $scales])
<script type="application/json" id="scales-catalog">@json($scales)</script>
