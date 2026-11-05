@php
    $plural = fn (int $count, string $one, string $many): string => $count.' '.($count === 1 ? $one : $many);
    $percent = fn (float $value): string => number_format($value, 1, ',', '.').'%';
    $hours = fn (float $value): string => number_format($value, 1, ',', '.').' h';
    $previousPeriod = $report['previous_period']['start'].' a '.$report['previous_period']['end'];
    $breakdown = $report['sessions_breakdown'];
    $chip = function (?float $variation, string $unit): string {
        if ($variation === null) {
            return 'Sem base de comparação no período anterior';
        }

        $tone = $variation == 0 ? 'muted' : ($variation > 0 ? 'success' : 'danger');
        $icon = $variation > 0 ? 'fa-arrow-up' : ($variation < 0 ? 'fa-arrow-down' : 'fa-equals');

        return '<span class="dash-chip dash-chip-'.$tone.'"><i class="fas '.$icon.'"></i>'.number_format(abs($variation), 1, ',', '.').$unit.'</span> vs. período anterior';
    };
@endphp

@if(!$report['configured'])
    <div class="dash-card mb-4">
        <div class="dash-empty" id="report-occupancy-not-configured">
            <i class="fas fa-clock"></i>
            Configure os dias e horários de atendimento para calcular a ocupação da sua agenda.
            <div class="mt-3">
                <a href="{{ route('settings.index') }}#horarios" class="btn dash-btn-soft"><i class="fas fa-cog me-2"></i>Configurar horários</a>
            </div>
        </div>
    </div>
@else
    @if($report['outside_hours'] > 0)
        <div class="report-alert is-info mb-4" id="report-occupancy-outside" role="status">
            <span class="report-alert-icon"><i class="fas fa-info-circle"></i></span>
            <div class="report-alert-body">
                <strong>{{ $plural($report['outside_hours'], 'sessão ficou', 'sessões ficaram') }} fora dos horários de atendimento configurados.</strong>
                <span>Elas contam nas horas agendadas, mas não na capacidade da agenda.</span>
            </div>
            <a href="{{ route('settings.index') }}#horarios" class="btn report-alert-btn">
                Revisar horários <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="dash-card report-kpi is-hero" id="report-kpi-occupancy">
                <div class="report-kpi-head">
                    <span class="report-kpi-icon"><i class="fas fa-chart-pie"></i></span>
                    <span class="dash-eyebrow">Taxa de ocupação</span>
                </div>
                <div class="dash-kpi-value">{{ $report['occupancy_rate'] !== null ? $percent($report['occupancy_rate']) : '—' }}</div>
                <div class="dash-progress report-kpi-progress"><span style="width: {{ $report['occupancy_rate'] ?? 0 }}%"></span></div>
                <span class="report-kpi-count">{{ $hours($report['booked_hours']) }} de {{ $hours($report['capacity_hours']) }} disponíveis</span>
                <div class="report-kpi-compare" title="Comparado ao período de {{ $previousPeriod }}">{!! $chip($report['variation'], ' p.p.') !!}</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="dash-card report-kpi" id="report-kpi-booked">
                <div class="report-kpi-head">
                    <span class="report-kpi-icon dash-tone-0"><i class="fas fa-hourglass-half"></i></span>
                    <span class="dash-eyebrow">Horas agendadas</span>
                </div>
                <div class="dash-kpi-value">{{ $hours($report['booked_hours']) }}</div>
                <span class="report-kpi-count">{{ $hours($report['free_hours']) }} sem sessão no período</span>
                <div class="report-kpi-compare" title="Comparado ao período de {{ $previousPeriod }}">{!! $chip($report['booked_variation'], '%') !!}</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="dash-card report-kpi" id="report-kpi-free">
                <div class="report-kpi-head">
                    <span class="report-kpi-icon dash-tone-1"><i class="fas fa-calendar-plus"></i></span>
                    <span class="dash-eyebrow">Horários livres</span>
                </div>
                <div class="dash-kpi-value">{{ $report['free_slots_upcoming'] }}</div>
                <span class="report-kpi-count">{{ $report['free_slots_upcoming'] === 1 ? 'disponível' : 'disponíveis' }} a partir de agora</span>
                <div class="report-kpi-compare">
                    @if($report['idle_slots_past'] > 0)
                        <span title="Horários de atendimento que já passaram sem sessão">{{ $plural($report['idle_slots_past'], 'ficou ocioso', 'ficaram ociosos') }}</span>
                    @endif
                    @if($report['free_slots_upcoming'] > 0)
                        <a href="{{ route('agenda.index') }}" class="report-kpi-link">Ver agenda <i class="fas fa-arrow-right"></i></a>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="dash-card report-kpi" id="report-kpi-sessions">
                <div class="report-kpi-head">
                    <span class="report-kpi-icon dash-tone-4"><i class="fas fa-user-clock"></i></span>
                    <span class="dash-eyebrow">Sessões no período</span>
                </div>
                <div class="dash-kpi-value">{{ $report['sessions'] }}</div>
                <div class="report-kpi-compare report-kpi-breakdown">
                    <span class="dash-chip dash-chip-success" title="Sessões realizadas">{{ $plural($breakdown['completed'], 'realizada', 'realizadas') }}</span>
                    <span class="dash-chip dash-chip-info" title="Agendadas ou confirmadas">{{ $plural($breakdown['upcoming'], 'agendada', 'agendadas') }}</span>
                    <span class="dash-chip dash-chip-danger" title="O horário estava reservado e conta como ocupado">{{ $plural($breakdown['no_show'], 'falta', 'faltas') }}</span>
                </div>
            </div>
        </div>
    </div>

    @if($report['sessions'] === 0)
        <div class="dash-card mb-4">
            <div class="dash-empty" id="report-occupancy-empty">
                <i class="fas fa-calendar-alt"></i>
                Nenhuma sessão agendada neste período.
                <div class="mt-3">
                    <a href="{{ route('agenda.index') }}" class="btn dash-btn-soft"><i class="fas fa-calendar-alt me-2"></i>Ver agenda</a>
                </div>
            </div>
        </div>
    @else
        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="dash-card h-100">
                    <h6 class="dash-title"><i class="fas fa-chart-column"></i>Ocupação por semana</h6>
                    <div id="occupancy-chart" style="min-height: 320px;"></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="dash-card h-100" id="report-occupancy-weekdays">
                    <h6 class="dash-title"><i class="fas fa-calendar-week"></i>Por dia da semana</h6>
                    @if($report['insight'])
                        <div class="report-insight mb-3" id="report-occupancy-insight">
                            <i class="fas fa-lightbulb"></i>
                            <span>{{ $report['insight'] }}</span>
                        </div>
                    @endif
                    @foreach($report['by_weekday'] as $weekday)
                        <div class="report-method">
                            <div class="report-method-head">
                                <span class="report-method-name">{{ $weekday['label'] }}</span>
                                <span class="report-method-percent">{{ $percent($weekday['rate']) }}</span>
                            </div>
                            <div class="dash-progress"><span style="width: {{ $weekday['rate'] }}%"></span></div>
                            <span class="report-method-detail">{{ $weekday['busy'] }} de {{ $plural($weekday['total'], 'horário ocupado', 'horários ocupados') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="dash-card mb-4" id="report-occupancy-heatmap">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h6 class="dash-title mb-0"><i class="fas fa-th"></i>Mapa de ocupação por horário</h6>
                <div class="report-heatmap-legend" aria-hidden="true">
                    <span>Livre</span><span class="report-heatmap-scale"></span><span>Cheio</span>
                </div>
            </div>
            <div class="report-heatmap-scroll">
                <table class="report-heatmap">
                    <thead>
                        <tr>
                            <th scope="col"><span class="visually-hidden">Dia</span></th>
                            @foreach($report['heatmap']['hours'] as $hour)
                                <th scope="col">{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}h</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['heatmap']['rows'] as $row)
                            <tr>
                                <th scope="row">{{ $row['label'] }}</th>
                                @foreach($row['cells'] as $index => $cell)
                                    @if($cell === null)
                                        <td class="is-off" title="Sem atendimento"></td>
                                    @else
                                        @php($label = $row['label'].' '.str_pad((string) $report['heatmap']['hours'][$index], 2, '0', STR_PAD_LEFT).'h: '.$cell['busy'].' de '.$plural($cell['total'], 'horário ocupado', 'horários ocupados').' ('.$percent($cell['rate']).')')
                                        <td class="{{ $cell['rate'] >= 55 ? 'is-strong' : '' }}" style="--heat: {{ round(0.08 + $cell['rate'] / 100 * 0.84, 2) }}" title="{{ $label }}" aria-label="{{ $label }}">{{ round($cell['rate']) }}%</td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endif
