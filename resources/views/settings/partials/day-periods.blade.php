@php
    // Dia salvo sem períodos (onboarding ou configuração antiga) vira um período com o início/fim do dia
    $periods = ! empty($saved['periods']) && is_array($saved['periods'])
        ? array_values($saved['periods'])
        : [['start' => $saved['start'] ?? $start, 'end' => $saved['end'] ?? $end]];
@endphp
<div class="cfg-slot-times cfg-periods" data-day="{{ $key }}" data-label="{{ $label }}">
    @foreach ($periods as $index => $period)
        @include('settings.partials.day-period', ['key' => $key, 'index' => $index, 'period' => $period, 'label' => $label, 'extra' => $index > 0, 'inputClass' => $inputClass ?? ''])
    @endforeach
    <button type="button" class="btn btn-link btn-sm px-0 cfg-period-add">
        <i class="fa-solid fa-plus me-1"></i> Adicionar período
    </button>
</div>
