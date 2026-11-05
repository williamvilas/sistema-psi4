@php
    // Modalidade geral; com "online e presencial", cada dia da semana pode restringir a um formato (partial day-mode)
    $currentMode = ($agendaSettings?->attendance_mode ?? \App\Enums\AttendanceModeEnum::ONLINE)->value;
    // Com `cards`, cada opção vira um cartão com ícone e descrição (onboarding, uma pergunta por tela)
    $cards ??= false;
    $cardDetails = [
        \App\Enums\AttendanceModeEnum::ONLINE->value => ['icon' => 'fa-solid fa-video', 'text' => 'Sessões por vídeo. O paciente recebe o link da chamada.'],
        \App\Enums\AttendanceModeEnum::IN_PERSON->value => ['icon' => 'fa-solid fa-location-dot', 'text' => 'No consultório. O paciente vê o endereço ao agendar.'],
        \App\Enums\AttendanceModeEnum::BOTH->value => ['icon' => 'fa-solid fa-layer-group', 'text' => 'Você escolhe o formato de cada dia e horário.'],
    ];
    $inPersonMode = \App\Enums\AttendanceModeEnum::from($currentMode)->hasInPerson();
@endphp
<div>
    <span id="attendanceModeLabel" class="form-label d-block {{ $cards ? 'visually-hidden' : '' }}">Como você atende?</span>
    <div class="cfg-choices {{ $cards ? 'is-cards' : '' }}" role="radiogroup" aria-labelledby="attendanceModeLabel">
        @foreach ([\App\Enums\AttendanceModeEnum::ONLINE, \App\Enums\AttendanceModeEnum::IN_PERSON, \App\Enums\AttendanceModeEnum::BOTH] as $mode)
            <input type="radio" class="btn-check attendance-mode" id="attendanceMode{{ $mode->name }}" name="attendance_mode" value="{{ $mode->value }}" autocomplete="off" @checked($mode->value === $currentMode)>
            @if ($cards)
                <label class="cfg-choice" for="attendanceMode{{ $mode->name }}">
                    <span class="cfg-choice-icon"><i class="{{ $cardDetails[$mode->value]['icon'] }}"></i></span>
                    <span class="cfg-choice-body">
                        <span class="cfg-choice-title">{{ $mode->getText() }}</span>
                        <span class="cfg-choice-text">{{ $cardDetails[$mode->value]['text'] }}</span>
                    </span>
                    <i class="fa-solid fa-circle-check cfg-choice-check"></i>
                </label>
            @else
                <label class="cfg-choice" for="attendanceMode{{ $mode->name }}">{{ $mode->getText() }}</label>
            @endif
        @endforeach
    </div>
    @unless ($cards)
        <small class="cfg-hint">No presencial, o paciente vê o endereço do consultório e não recebe link de vídeo. Com os dois, você pode escolher o formato de cada dia.</small>
    @endunless
    @if ($askAddressNextStep ?? false)
        <small class="cfg-hint {{ $inPersonMode ? '' : 'd-none' }}" id="ob-address-hint"><i class="fa-solid fa-circle-info me-1"></i>No próximo passo, você informa o endereço do consultório.</small>
    @endif
</div>
