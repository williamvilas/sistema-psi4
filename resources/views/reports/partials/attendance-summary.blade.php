@php
    $plural = fn (int $count, string $one, string $many): string => $count.' '.($count === 1 ? $one : $many);
    $percent = fn (float $value): string => number_format($value, 1, ',', '.').'%';
    $total = $report['total'];
    $kpis = [
        ['id' => 'report-kpi-attendance', 'label' => 'Comparecimento', 'icon' => 'fa-user-check', 'tone' => 'dash-tone-5', 'hero' => true, 'upIsGood' => true, 'unit' => 'p.p.',
            'value' => $percent($report['attendance']['rate']), 'variation' => $report['attendance']['variation'],
            'detail' => $report['attendance']['count'].' de '.$plural($total, 'sessão finalizada', 'sessões finalizadas')],
        ['id' => 'report-kpi-no-show', 'label' => 'Taxa de faltas', 'icon' => 'fa-user-times', 'tone' => 'dash-tone-2', 'hero' => false, 'upIsGood' => false, 'unit' => 'p.p.',
            'value' => $percent($report['no_show']['rate']), 'variation' => $report['no_show']['variation'],
            'detail' => $plural($report['no_show']['count'], 'falta', 'faltas')],
        ['id' => 'report-kpi-canceled', 'label' => 'Taxa de cancelamentos', 'icon' => 'fa-calendar-times', 'tone' => 'dash-tone-3', 'hero' => false, 'upIsGood' => false, 'unit' => 'p.p.',
            'value' => $percent($report['canceled']['rate']), 'variation' => $report['canceled']['variation'],
            'detail' => $plural($report['canceled']['count'], 'cancelamento', 'cancelamentos')],
        ['id' => 'report-kpi-lost', 'label' => 'Perda estimada', 'icon' => 'fa-hand-holding-usd', 'tone' => 'dash-tone-1', 'hero' => false, 'upIsGood' => false, 'unit' => '%',
            'value' => formatCurrency($report['lost_revenue']['total']), 'variation' => $report['lost_revenue']['variation'],
            'detail' => 'em '.$plural($report['lost_revenue']['count'], 'falta', 'faltas')],
    ];
    $breakdown = [
        ['label' => 'Realizadas', 'tone' => '', 'data' => $report['attendance']],
        ['label' => 'Faltas', 'tone' => 'is-danger', 'data' => $report['no_show']],
        ['label' => 'Cancelamentos', 'tone' => 'is-warning', 'data' => $report['canceled']],
    ];
    $previousPeriod = $report['previous_period']['start'].' a '.$report['previous_period']['end'];
    $absences = $report['no_show']['count'] + $report['canceled']['count'];
@endphp

@if($report['pending'] > 0)
    <div class="report-alert mb-4" id="report-attendance-pending" role="status">
        <span class="report-alert-icon"><i class="fas fa-exclamation-triangle"></i></span>
        <div class="report-alert-body">
            <strong>{{ $plural($report['pending'], 'sessão está', 'sessões estão') }} sem presença registrada neste período.</strong>
            <span>Elas ficam fora das taxas, então os números podem estar incompletos.</span>
        </div>
        <a href="{{ route('agenda.index', ['pending_attendance' => 1, 'date' => $report['period']['start'].' - '.$report['period']['end']]) }}" class="btn report-alert-btn">
            Registrar presenças <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>
@endif

<div class="row g-3 mb-4">
    @foreach($kpis as $kpi)
        @php($variation = $kpi['variation'])
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="dash-card report-kpi {{ $kpi['hero'] ? 'is-hero' : '' }}" id="{{ $kpi['id'] }}">
                <div class="report-kpi-head">
                    <span class="report-kpi-icon {{ $kpi['tone'] }}"><i class="fas {{ $kpi['icon'] }}"></i></span>
                    <span class="dash-eyebrow">{{ $kpi['label'] }}</span>
                </div>
                <div class="dash-kpi-value">{{ $kpi['value'] }}</div>
                <span class="report-kpi-count">{{ $kpi['detail'] }}</span>
                <div class="report-kpi-compare" title="Comparado ao período de {{ $previousPeriod }}">
                    @if($variation === null)
                        Sem base de comparação no período anterior
                    @else
                        @php($tone = $variation == 0 ? 'muted' : (($variation > 0) === $kpi['upIsGood'] ? 'success' : 'danger'))
                        <span class="dash-chip dash-chip-{{ $tone }}">
                            <i class="fas {{ $variation > 0 ? 'fa-arrow-up' : ($variation < 0 ? 'fa-arrow-down' : 'fa-equals') }}"></i>{{ number_format(abs($variation), 1, ',', '.') }}{{ $kpi['unit'] === '%' ? '%' : ' '.$kpi['unit'] }}
                        </span>
                        vs. período anterior
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

@if($total === 0)
    <div class="dash-card mb-4">
        <div class="dash-empty" id="report-attendance-empty">
            <i class="fas fa-calendar-check"></i>
            Nenhuma sessão finalizada neste período.
            <div class="mt-3">
                <a href="{{ route('agenda.index') }}" class="btn dash-btn-soft"><i class="fas fa-calendar-alt me-2"></i>Ver agenda</a>
            </div>
        </div>
    </div>
@else
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="dash-card h-100">
                <h6 class="dash-title"><i class="fas fa-chart-column"></i>Faltas e cancelamentos por dia da semana</h6>
                @if($absences === 0)
                    <div class="dash-empty" id="report-attendance-no-absences">
                        <i class="fas fa-smile"></i>
                        Nenhuma falta ou cancelamento neste período. Ótimo trabalho!
                    </div>
                @else
                    <div id="attendance-chart" style="min-height: 300px;"></div>
                @endif
            </div>
        </div>
        <div class="col-lg-4">
            <div class="dash-card h-100">
                <h6 class="dash-title"><i class="fas fa-clipboard-check"></i>Resumo do período</h6>
                @if($report['insight'])
                    <div class="report-insight mb-3" id="report-attendance-insight">
                        <i class="fas fa-lightbulb"></i>
                        <span>{{ $report['insight'] }}</span>
                    </div>
                @endif
                @foreach($breakdown as $item)
                    <div class="report-method">
                        <div class="report-method-head">
                            <span class="report-method-name">{{ $item['label'] }}</span>
                            <span class="report-method-percent">{{ $percent($item['data']['rate']) }}</span>
                        </div>
                        <div class="dash-progress {{ $item['tone'] }}"><span style="width: {{ $item['data']['rate'] }}%"></span></div>
                        <span class="report-method-detail">{{ $plural($item['data']['count'], 'sessão', 'sessões') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

<div class="dash-card mb-4" id="report-attendance-patients">
    <h6 class="dash-title"><i class="fas fa-users"></i>Pacientes com mais faltas</h6>
    @if(empty($report['top_patients']))
        <div class="dash-empty">
            <i class="fas fa-user-check"></i>
            Nenhuma falta registrada no período.
        </div>
    @else
        @foreach($report['top_patients'] as $patient)
            <div class="dash-row report-patient">
                @include('components.avatar-initials', ['name' => $patient['name'] ?? '?'])
                <div class="dash-row-body">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        @if($patient['name'])
                            <a href="{{ route('patients.show', $patient['id']) }}" class="dash-row-name">{{ $patient['name'] }}</a>
                        @else
                            <span class="dash-row-name">Paciente removido</span>
                        @endif
                        @if($patient['attention'])
                            <span class="dash-chip dash-chip-danger" title="Faltou em pelo menos metade das sessões do período"><i class="fas fa-exclamation-circle"></i>Atenção</span>
                        @endif
                    </div>
                    <span class="dash-row-detail">
                        {{ $plural($patient['no_show'], 'falta', 'faltas') }}
                        @if($patient['canceled'] > 0) · {{ $plural($patient['canceled'], 'cancelamento', 'cancelamentos') }} @endif
                        · de {{ $plural($patient['total'], 'sessão', 'sessões') }}
                    </span>
                    <div class="dash-progress is-danger report-patient-progress"><span style="width: {{ $patient['rate'] }}%"></span></div>
                </div>
                <div class="report-patient-rate">
                    <strong>{{ $percent($patient['rate']) }}</strong>
                    <small>de faltas</small>
                </div>
            </div>
        @endforeach
    @endif
</div>
