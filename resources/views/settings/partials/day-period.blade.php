{{-- Um período de atendimento do dia (início, fim e formato). Os extras (2º em diante) só existem com "online e presencial". --}}
<div class="cfg-period {{ $extra ? 'is-extra' : '' }}">
    <input type="time" class="form-control {{ $inputClass ?? '' }} cfg-time" name="agenda_days[{{ $key }}][periods][{{ $index }}][start]" value="{{ substr($period['start'] ?? '', 0, 5) }}" aria-label="Início {{ $label }}">
    <span>até</span>
    <input type="time" class="form-control {{ $inputClass ?? '' }} cfg-time" name="agenda_days[{{ $key }}][periods][{{ $index }}][end]" value="{{ substr($period['end'] ?? '', 0, 5) }}" aria-label="Fim {{ $label }}">
    @include('settings.partials.day-mode', ['key' => $key, 'index' => $index, 'mode' => $period['mode'] ?? null, 'label' => $label])
    @if ($extra)
        <button type="button" class="dash-round-btn dash-round-muted cfg-period-remove" title="Remover período" aria-label="Remover período de {{ $label }}">
            <i class="fa-solid fa-xmark"></i>
        </button>
    @endif
</div>
