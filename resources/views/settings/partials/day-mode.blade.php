{{-- Formato do período: só aparece com "online e presencial" (schedule-settings.js) --}}
<select class="form-select form-select-43 form-select-sm cfg-day-mode" name="agenda_days[{{ $key }}][periods][{{ $index }}][mode]" aria-label="Formato de atendimento em {{ $label }}">
    @foreach ([\App\Enums\AttendanceModeEnum::BOTH, \App\Enums\AttendanceModeEnum::ONLINE, \App\Enums\AttendanceModeEnum::IN_PERSON] as $option)
        <option value="{{ $option->value }}" @selected(($mode ?? \App\Enums\AttendanceModeEnum::BOTH->value) === $option->value)>{{ __('agenda.day_mode.'.$option->value) }}</option>
    @endforeach
</select>
