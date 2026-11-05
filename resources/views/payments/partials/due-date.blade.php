@php
    $today = now($timezone)->startOfDay();
    $due = $order->due_date ? \Carbon\Carbon::parse($order->due_date->toDateString(), $timezone) : null;
    $days = $due ? (int) round($today->diffInDays($due, false)) : null;

    if ($status === \App\Enums\PaymentOrderStatusEnum::PAID && $order->paid_at) {
        [$icon, $tone, $text] = ['fa-circle-check', 'success', 'Pago em '.$order->paid_at->timezone($timezone)->format('d/m/Y')];
    } elseif (! $due) {
        [$icon, $tone, $text] = ['fa-calendar', 'muted', 'Sem vencimento'];
    } elseif ($status === \App\Enums\PaymentOrderStatusEnum::PENDING) {
        $text = match (true) {
            $days <= 0 => 'Vence hoje',
            $days === 1 => 'Vence amanhã',
            default => 'Vence em '.$days.' dias',
        };
        [$icon, $tone] = ['fa-calendar', $days <= 3 ? 'warning' : 'muted'];
    } elseif ($status === \App\Enums\PaymentOrderStatusEnum::EXPIRED) {
        $text = $days < 0 ? 'Venceu há '.abs($days).' '.(abs($days) === 1 ? 'dia' : 'dias') : 'Venceu em '.$due->format('d/m/Y');
        [$icon, $tone] = ['fa-calendar-xmark', 'danger'];
    } else {
        [$icon, $tone, $text] = ['fa-calendar', 'muted', 'Vencimento '.$due->format('d/m/Y')];
    }
@endphp
<span class="pay-due pay-due-{{ $tone }}" @if($due) title="Vencimento: {{ $due->format('d/m/Y') }}" @endif>
    <i class="far {{ $icon }} me-2"></i>{{ $text }}
</span>
