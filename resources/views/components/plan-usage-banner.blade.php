{{-- Saldo do mês do plano Grátis: aparece a partir do penúltimo uso de algum limite (PlanLimitService) --}}
@php
    $usageUser = \Illuminate\Support\Facades\Auth::user();
    $usage = $usageUser ? app(\App\Services\PlanLimitService::class)->usage($usageUser) : [];
    $reached = array_filter($usage, fn (array $item) => $item['limit'] > 0 && $item['used'] >= $item['limit']);
    $near = array_filter($usage, fn (array $item) => $item['limit'] > 0 && $item['used'] === $item['limit'] - 1);
    $usageItems = fn (array $items) => implode(', ', array_map(
        fn (string $key, array $item) => __('plans.usage_labels.'.$key).' ('.$item['used'].' de '.$item['limit'].')',
        array_keys($items),
        $items,
    ));
@endphp

@if($reached)
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" role="status" id="plan-usage-banner">
        <span>
            <i class="fas fa-hourglass-end me-1" aria-hidden="true"></i>
            Seu plano Grátis chegou ao limite deste mês em: {{ $usageItems($reached) }}.
            @isset($reached['total_agenda'])
                Seu link de agendamento online também fica pausado até lá.
            @endisset
            O saldo volta no dia 1º. Seus registros continuam seus, guardados e cifrados. Para seguir sem limites agora, conheça o Profissional.
        </span>
        <a href="{{ route('plans.index') }}" class="btn btn-sm btn-primary">Fazer upgrade</a>
    </div>
@elseif($near)
    <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2" role="status" id="plan-usage-banner">
        <span>
            <i class="fas fa-info-circle me-1" aria-hidden="true"></i>
            Falta pouco para o limite do plano Grátis neste mês: {{ $usageItems($near) }}.
        </span>
        <a href="{{ route('plans.index') }}" class="btn btn-sm btn-outline-primary">Ver os planos</a>
    </div>
@endif
