@php
    $tiles = [
        ['label' => 'A receber', 'icon' => 'fa-hourglass-half', 'data' => $summary['receivable'], 'tone' => '', 'hint' => 'pendente',
            'status' => \App\Enums\PaymentOrderStatusEnum::PENDING->value],
        ['label' => 'Recebido no mês', 'icon' => 'fa-circle-check', 'data' => $summary['received_month'], 'tone' => 'is-success', 'hint' => 'paga',
            'status' => \App\Enums\PaymentOrderStatusEnum::PAID->value],
        ['label' => 'Vencidas', 'icon' => 'fa-exclamation-circle', 'data' => $summary['overdue'], 'tone' => $summary['overdue']['count'] > 0 ? 'is-alert' : '', 'hint' => 'vencida',
            'status' => \App\Enums\PaymentOrderStatusEnum::EXPIRED->value],
    ];
@endphp

<div class="dash-grid pay-summary mb-4">
    @foreach($tiles as $tile)
        @php($count = $tile['data']['count'])
        <button type="button" class="dash-tile pay-tile {{ $tile['tone'] }}" data-status="{{ $tile['status'] }}" onclick="filterPaymentOrdersByStatus(this.dataset.status)" title="Ver cobranças">
            <span class="dash-tile-icon"><i class="fas {{ $tile['icon'] }}"></i></span>
            <span class="pay-tile-body">
                <span class="dash-tile-label mt-0">{{ $tile['label'] }}</span>
                <span class="pay-tile-value">{{ formatCurrency($tile['data']['total']) }}</span>
                <small class="dash-tile-hint mt-0">
                    {{ $count }} {{ $count === 1 ? 'cobrança '.$tile['hint'] : 'cobranças '.$tile['hint'].'s' }}
                </small>
            </span>
        </button>
    @endforeach
</div>
