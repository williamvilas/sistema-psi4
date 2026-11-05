@php
    $plural = fn (int $count, string $one, string $many): string => $count.' '.($count === 1 ? $one : $many);
    $kpis = [
        ['id' => 'report-kpi-paid', 'label' => 'Recebido', 'icon' => 'fa-hand-holding-usd', 'tone' => 'dash-tone-5', 'hero' => true, 'upIsGood' => true,
            'data' => $report['paid'], 'detail' => $plural($report['paid']['count'], 'pagamento', 'pagamentos')],
        ['id' => 'report-kpi-pending', 'label' => 'Pendente', 'icon' => 'fa-hourglass-half', 'tone' => 'dash-tone-3', 'hero' => false, 'upIsGood' => false,
            'data' => $report['pending'], 'detail' => $plural($report['pending']['count'], 'cobrança', 'cobranças').' (inclui expiradas)'],
        ['id' => 'report-kpi-ticket', 'label' => 'Ticket médio', 'icon' => 'fa-receipt', 'tone' => 'dash-tone-1', 'hero' => false, 'upIsGood' => true,
            'data' => $report['average_ticket'], 'detail' => 'por pagamento recebido'],
        ['id' => 'report-kpi-refunded', 'label' => 'Reembolsado', 'icon' => 'fa-undo-alt', 'tone' => 'dash-tone-2', 'hero' => false, 'upIsGood' => false,
            'data' => $report['refunded'], 'detail' => $plural($report['refunded']['count'], 'pagamento', 'pagamentos')],
    ];
    $granularity = ['day' => 'por dia', 'week' => 'por semana', 'month' => 'por mês', 'year' => 'por ano'][$report['received']['granularity']];
    $previousPeriod = $report['previous_period']['start'].' a '.$report['previous_period']['end'];
@endphp

<div class="row g-3 mb-4">
    @foreach($kpis as $kpi)
        @php($variation = $kpi['data']['variation'])
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="dash-card report-kpi {{ $kpi['hero'] ? 'is-hero' : '' }}" id="{{ $kpi['id'] }}">
                <div class="report-kpi-head">
                    <span class="report-kpi-icon {{ $kpi['tone'] }}"><i class="fas {{ $kpi['icon'] }}"></i></span>
                    <span class="dash-eyebrow">{{ $kpi['label'] }}</span>
                </div>
                <div class="dash-kpi-value">{{ formatCurrency($kpi['data']['total']) }}</div>
                <span class="report-kpi-count">{{ $kpi['detail'] }}</span>
                <div class="report-kpi-compare" title="Comparado ao período de {{ $previousPeriod }}">
                    @if($variation === null)
                        Sem base de comparação no período anterior
                    @else
                        @php($tone = $variation == 0 ? 'muted' : (($variation > 0) === $kpi['upIsGood'] ? 'success' : 'danger'))
                        <span class="dash-chip dash-chip-{{ $tone }}">
                            <i class="fas {{ $variation > 0 ? 'fa-arrow-up' : ($variation < 0 ? 'fa-arrow-down' : 'fa-equals') }}"></i>{{ number_format(abs($variation), 1, ',', '.') }}%
                        </span>
                        vs. período anterior
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

@if($report['paid']['count'] === 0)
    <div class="dash-card mb-4">
        <div class="dash-empty" id="report-financial-empty">
            <i class="fas fa-chart-column"></i>
            Nenhum pagamento recebido neste período.
            <div class="mt-3">
                <a href="{{ route('payments.create') }}" class="btn dash-btn-soft"><i class="fas fa-file-invoice-dollar me-2"></i>Nova cobrança</a>
            </div>
        </div>
    </div>
@else
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="dash-card h-100">
                <h6 class="dash-title"><i class="fas fa-chart-column"></i><span>Recebimentos no período <small class="dash-total fw-normal">({{ $granularity }})</small></span></h6>
                <div id="received-period-chart" style="min-height: 320px;"></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="dash-card h-100">
                <h6 class="dash-title"><i class="fas fa-wallet"></i>Meios de pagamento</h6>
                <div id="report-payment-methods">
                    @foreach($report['payment_methods'] as $method)
                        <div class="report-method">
                            <div class="report-method-head">
                                <span class="report-method-name">{{ $method['label'] }}</span>
                                <span class="report-method-percent">{{ number_format($method['percent'], 1, ',', '.') }}%</span>
                            </div>
                            <div class="dash-progress"><span style="width: {{ $method['percent'] }}%"></span></div>
                            <span class="report-method-detail">{{ $plural($method['count'], 'pagamento', 'pagamentos') }} · {{ formatCurrency($method['total']) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif
