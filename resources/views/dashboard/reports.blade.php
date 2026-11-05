@php
    $timezone = auth()->user()->timezone;
    $todayDate = now($timezone)->format('d/m/Y');
    $scheduledToday = $report['scheduled_today'];
    $pending = $report['pending_attendance'];
    $pendingPayments = $report['pending_payments'];
    $birthdaysToday = $report['birthdays_today'];
    $kpis = $report['kpis'];
    $todaySessions = $report['today_sessions'];
    $overdue = $report['overdue_payments'];
    $statusTones = [
        \App\Enums\AgendaStatusEnum::SCHEDULED->value => 'info',
        \App\Enums\AgendaStatusEnum::COMPLETED->value => 'success',
        \App\Enums\AgendaStatusEnum::NO_SHOW->value => 'danger',
        \App\Enums\AgendaStatusEnum::CANCELED->value => 'warning',
    ];
    $tiles = [
        ['id' => 'sessoesAgendadasHojeCount', 'label' => 'Sessões hoje', 'icon' => 'fa-calendar-alt', 'count' => $scheduledToday, 'alert' => false,
            'href' => route('agenda.index', ['status' => \App\Enums\AgendaStatusEnum::SCHEDULED->value, 'date' => $todayDate.' - '.$todayDate])],
        ['id' => 'sessoesPendentesCount', 'label' => 'Sessões pendentes', 'icon' => 'fa-calendar-check', 'count' => $pending, 'alert' => true,
            'href' => route('agenda.index', ['pending_attendance' => 1])],
        ['id' => 'pagamentosPendentesCount', 'label' => 'Pagamentos pendentes', 'icon' => 'fa-money-bill-wave', 'count' => $pendingPayments, 'alert' => true,
            'href' => route('payments.index', ['status' => \App\Enums\PaymentOrderStatusEnum::PENDING->value])],
        ['id' => 'aniversariantesCount', 'label' => 'Aniversariantes', 'icon' => 'fa-birthday-cake', 'count' => $birthdaysToday, 'alert' => false,
            'href' => route('patients.index', ['birthday_celebrants' => $todayDate.' - '.$todayDate])],
    ];
    $shortcuts = [
        ['label' => 'Agenda', 'icon' => 'fa-calendar-plus', 'href' => route('agenda.index')],
        ['label' => 'Novo paciente', 'icon' => 'fa-user-plus', 'href' => route('patients.create')],
        ['label' => 'Novo prontuário', 'icon' => 'fa-notes-medical', 'href' => route('medical-records.create')],
        ['label' => 'Nova cobrança', 'icon' => 'fa-file-invoice-dollar', 'href' => route('payments.create')],
    ];
@endphp

<div class="dash-grid dash-tiles mb-4">
    @foreach($tiles as $tile)
        <a href="{{ $tile['count'] > 0 ? $tile['href'] : 'javascript:void(0)' }}" class="dash-tile {{ $tile['count'] > 0 ? '' : 'is-empty' }} {{ $tile['alert'] && $tile['count'] > 0 ? 'is-alert' : '' }}">
            <span class="dash-tile-icon"><i class="fas {{ $tile['icon'] }}"></i></span>
            <span class="dash-tile-count" id="{{ $tile['id'] }}">{{ $tile['count'] }}</span>
            <span class="dash-tile-label">{{ $tile['label'] }}</span>
            <small class="dash-tile-hint">
                @if($tile['count'] > 0) Ver detalhes <i class="fas fa-arrow-right"></i> @else <i class="fas fa-check"></i> Tudo em dia @endif
            </small>
        </a>
    @endforeach
</div>

<div class="dash-grid dash-shortcuts mb-4">
    @foreach($shortcuts as $shortcut)
        <a href="{{ $shortcut['href'] }}" class="dash-shortcut">
            <span class="dash-shortcut-icon"><i class="fas {{ $shortcut['icon'] }}"></i></span>{{ $shortcut['label'] }}
        </a>
    @endforeach
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="dash-card h-100">
            <span class="dash-eyebrow">Recebido no mês</span>
            <div class="dash-kpi-value">{{ formatCurrency($kpis['revenue']['total']) }}</div>
            @if($kpis['revenue']['variation'] !== null)
                <span class="dash-chip {{ $kpis['revenue']['variation'] >= 0 ? 'dash-chip-success' : 'dash-chip-danger' }}">
                    <i class="fas {{ $kpis['revenue']['variation'] >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>{{ abs($kpis['revenue']['variation']) }}%
                </span>
                <small class="text-muted ms-1">vs. mesmo período do mês anterior</small>
            @else
                <small class="text-muted">Sem base de comparação no mês anterior</small>
            @endif
        </div>
    </div>
    <div class="col-lg-4">
        <div class="dash-card h-100">
            <span class="dash-eyebrow">Comparecimento (30 dias)</span>
            <div class="dash-kpi-value">{{ $kpis['attendance']['rate'] !== null ? $kpis['attendance']['rate'].'%' : '—' }}</div>
            <div class="dash-progress"><span style="width: {{ $kpis['attendance']['rate'] ?? 0 }}%"></span></div>
            @if($kpis['attendance']['variation'] !== null)
                <span class="dash-chip {{ $kpis['attendance']['variation'] >= 0 ? 'dash-chip-success' : 'dash-chip-danger' }}">
                    <i class="fas {{ $kpis['attendance']['variation'] >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>{{ abs($kpis['attendance']['variation']) }} p.p.
                </span>
                <small class="text-muted ms-1">vs. 30 dias anteriores</small>
            @else
                <small class="text-muted">Sem sessões encerradas para comparar</small>
            @endif
        </div>
    </div>
    <div class="col-lg-4">
        <div class="dash-card h-100">
            <span class="dash-eyebrow">Ocupação da semana</span>
            <div class="dash-kpi-value">{{ $kpis['occupancy']['rate'] !== null ? $kpis['occupancy']['rate'].'%' : '—' }}</div>
            <div class="dash-progress"><span style="width: {{ $kpis['occupancy']['rate'] ?? 0 }}%"></span></div>
            @if($kpis['occupancy']['rate'] !== null)
                <small class="text-muted">{{ $kpis['occupancy']['free_hours'] }}h livres na semana</small>
            @else
                <small class="text-muted">Configure sua agenda para calcular</small>
            @endif
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="dash-card h-100">
            <h6 class="dash-title"><i class="fas fa-chart-bar"></i>Sessões por semana</h6>
            <div id="dashboard-weekly-sessions" style="min-height: 300px;"></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="dash-card h-100">
            <h6 class="dash-title"><i class="fas fa-calendar-day"></i>Sessões de hoje
                @if(count($todaySessions) > 0)<span class="dash-count">{{ count($todaySessions) }}</span>@endif
            </h6>
            @forelse($todaySessions as $session)
                <div class="dash-row">
                    <span class="dash-time">{{ $session['time'] }}</span>
                    <div class="dash-row-body">
                        <a href="{{ route('patients.show', ['id' => encrypt($session['patient_id'])]) }}" class="dash-row-name">{{ $session['name'] }}</a>
                        <div><span class="dash-chip dash-chip-{{ $statusTones[$session['status']] ?? 'info' }}">{{ __('agenda_status.'.$session['status']) }}</span></div>
                    </div>
                    @if(! empty($session['room_link']))
                        <a href="{{ $session['room_link'] }}" target="_blank" rel="noopener" class="dash-round-btn dash-round-btn-primary" title="Entrar na sala"><i class="fas fa-video"></i></a>
                    @endif
                </div>
            @empty
                <div class="dash-empty"><i class="fas fa-mug-hot"></i>Nenhuma sessão para hoje</div>
            @endforelse
        </div>
    </div>
</div>

@php($alertBlocks = [
    ['key' => 'abandonment', 'title' => 'Risco de abandono', 'icon' => 'fa-user-clock', 'hint' => 'Sem sessão há mais de 30 dias e nada agendado'],
    ['key' => 'no_show_streak', 'title' => 'Faltas seguidas', 'icon' => 'fa-user-times', 'hint' => 'Faltaram às últimas sessões'],
    ['key' => 'scale_overdue', 'title' => 'Reaplicar escala', 'icon' => 'fa-clipboard-list', 'hint' => 'Em atendimento e sem escala há mais de 30 dias'],
])
<div class="row g-3 mb-4">
    <div class="col-lg-6 col-xl-3">
        <div class="dash-card h-100">
            <h6 class="dash-title {{ $overdue['count'] > 0 ? 'is-alert' : '' }}"><i class="fas fa-exclamation-circle"></i>Cobranças vencidas
                @if($overdue['count'] > 0)<span class="dash-count">{{ $overdue['count'] }}</span>@endif
            </h6>
            @if($overdue['count'] > 0)
                <small class="text-danger fw-semibold d-block mb-2">{{ formatCurrency($overdue['total']) }} em atraso</small>
            @endif
            @forelse($overdue['items'] as $order)
                @include('dashboard.partials.patient-row', [
                    'name' => $order['name'],
                    'href' => route('payments.show', ['id' => encrypt($order['id'])]),
                    'detail' => formatCurrency($order['amount']).' · '.$order['days_overdue'].' '.($order['days_overdue'] === 1 ? 'dia' : 'dias').' de atraso',
                    'phone' => $order['phone'],
                    'whatsappTitle' => 'Cobrar pelo WhatsApp',
                ])
            @empty
                <div class="dash-empty"><i class="fas fa-circle-check"></i>Nenhuma cobrança vencida</div>
            @endforelse
            @if($overdue['count'] > count($overdue['items']))
                <a href="{{ route('payments.index', ['status' => \App\Enums\PaymentOrderStatusEnum::EXPIRED->value]) }}" class="dash-more">Ver todas <i class="fas fa-arrow-right"></i></a>
            @endif
        </div>
    </div>
    @foreach($alertBlocks as $block)
        @php($alert = $report['alerts'][$block['key']])
        <div class="col-lg-6 col-xl-3">
            <div class="dash-card h-100">
                <h6 class="dash-title {{ $alert['count'] > 0 ? 'is-alert' : '' }}"><i class="fas {{ $block['icon'] }}"></i>{{ $block['title'] }}
                    @if($alert['count'] > 0)<span class="dash-count">{{ $alert['count'] }}</span>@endif
                </h6>
                <small class="text-muted d-block mb-2">{{ $block['hint'] }}</small>
                @forelse($alert['items'] as $item)
                    @include('dashboard.partials.patient-row', [
                        'name' => $item['name'],
                        'href' => route('patients.show', ['id' => encrypt($item['patient_id'])]),
                        'detail' => $item['detail'],
                        'phone' => $item['phone'],
                        'whatsappTitle' => 'Chamar no WhatsApp',
                    ])
                @empty
                    <div class="dash-empty"><i class="fas fa-circle-check"></i>Nenhum paciente neste alerta</div>
                @endforelse
                @if($alert['count'] > count($alert['items']))
                    <small class="dash-more">e mais {{ $alert['count'] - count($alert['items']) }}</small>
                @endif
            </div>
        </div>
    @endforeach
</div>
