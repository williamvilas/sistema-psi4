@extends('layouts.guest')
@section('title', 'Configuração inicial')
@section('content')
    @php
        $user = auth()->user();
        $firstName = \Illuminate\Support\Str::of($user->name)->explode(' ')->first();
        $weekDays = [
            'seg' => ['label' => 'Segunda-feira', 'enabled' => true],
            'ter' => ['label' => 'Terça-feira', 'enabled' => true],
            'qua' => ['label' => 'Quarta-feira', 'enabled' => true],
            'qui' => ['label' => 'Quinta-feira', 'enabled' => true],
            'sex' => ['label' => 'Sexta-feira', 'enabled' => true],
            'sab' => ['label' => 'Sábado', 'enabled' => false],
            'dom' => ['label' => 'Domingo', 'enabled' => false],
        ];
        $savedDays = $agendaSettings?->agenda_days;
        $meals = [
            ['key' => 'lunch', 'switch' => 'almocoSwitch', 'fields' => 'almocoFields', 'icon' => 'fa-solid fa-utensils', 'label' => 'Almoço', 'enabled' => $agendaSettings?->lunch_enabled ?? false, 'start' => $agendaSettings?->lunch_start ?? '12:00', 'end' => $agendaSettings?->lunch_end ?? '13:00'],
            ['key' => 'dinner', 'switch' => 'jantaSwitch', 'fields' => 'jantaFields', 'icon' => 'fa-solid fa-moon', 'label' => 'Jantar', 'enabled' => $agendaSettings?->dinner_enabled ?? false, 'start' => $agendaSettings?->dinner_start ?? '19:00', 'end' => $agendaSettings?->dinner_end ?? '20:00'],
        ];
        $holidays = $agendaSettings?->holidays ?? collect();
        $clinicName = $psychologistSettings?->clinic_name ?: 'Dr(a). '.preg_replace('/^\s*(dr|dra)\.?\s+/i', '', (string) $user->name);
        $crp = $psychologistSettings?->crp ?: preg_replace('/\D/', '', (string) $user->crp);
        $inPerson = (bool) $agendaSettings?->attendance_mode?->hasInPerson();
        // Horários sugeridos por modalidade; trocar a modalidade reaplica enquanto a grade não for editada (onboarding.js)
        $defaultHours = [
            'online' => [['start' => '09:00', 'end' => '23:00']],
            'in_person' => [['start' => '09:00', 'end' => '18:00']],
            'both' => [['start' => '09:00', 'end' => '18:00', 'mode' => 'both'], ['start' => '19:00', 'end' => '23:00', 'mode' => 'online']],
        ];
        $steps = [1 => 'Agenda', 2 => 'Seu perfil', 3 => 'Agendamento online'];
        $agendaSubsteps = [1 => 'Duração da consulta', 2 => 'Intervalo entre consultas', 3 => 'Como você atende?', 4 => 'Dias e horários'];
    @endphp

    @include('components.css-dash')
    @include('settings.partials.settings-css')
    <style>
        .ob-wrapper { min-height: 100vh; display: flex; align-items: flex-start; justify-content: center; padding: 32px 16px 120px; }
        .ob-page { width: 100%; max-width: 780px; }
        .ob-top { position: relative; text-align: center; margin-bottom: 1.25rem; }
        .ob-logout { position: absolute; top: 0; right: 0; }
        .ob-logout .btn { color: var(--dash-muted); font-weight: 600; text-decoration: none; }
        .ob-logout .btn:hover { color: var(--dash-ink); }
        .input-lp{ font-size: 1.20rem; background: #cccc; }

        /* Indicador de etapas */
        .ob-steps { display: flex; align-items: flex-start; justify-content: center; list-style: none; padding: 0; margin: 1.25rem auto 0; max-width: 520px; }
        .ob-steps-item { flex: 1; display: flex; flex-direction: column; align-items: center; gap: .4rem; position: relative; color: var(--dash-muted); font-weight: 600; font-size: .95rem; }
        .ob-steps-item:not(:first-child)::before { content: ''; position: absolute; top: 17px; right: calc(50% + 24px); left: calc(-50% + 24px); height: 3px; border-radius: 2px; background: #dfe5ec; transition: background .3s; }
        .ob-steps-item.is-done:not(:first-child)::before, .ob-steps-item.is-active:not(:first-child)::before { background: var(--dash-primary); }
        .ob-steps-dot { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #fff; border: 2px solid #dfe5ec; color: var(--dash-muted); font-weight: 700; transition: all .25s; }
        .ob-steps-dot .fa-check { display: none; }
        .ob-steps-item.is-active { color: var(--dash-ink); }
        .ob-steps-item.is-active .ob-steps-dot { background: var(--dash-primary); border-color: var(--dash-primary); color: #fff; box-shadow: 0 0 0 5px var(--dash-primary-soft); }
        .ob-steps-item.is-done { color: var(--dash-primary); }
        .ob-steps-item.is-done .ob-steps-dot { background: var(--dash-primary-soft); border-color: var(--dash-primary); color: var(--dash-primary); }
        .ob-steps-item.is-done .ob-steps-num { display: none; }
        .ob-steps-item.is-done .ob-steps-dot .fa-check { display: inline; }

        /* Etapas */
        .ob-step { display: none; }
        .ob-step.active { display: block; animation: ob-step-in .3s ease-out; }
        @keyframes ob-step-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .ob-page .cfg-head { flex-wrap: nowrap; align-items: flex-start; }
        .ob-page .cfg-item-new { display: none !important; } /* nada é salvo até "Continuar" */
        .ob-page .cfg-item.is-new { border-color: var(--dash-line); background: #fbfcfd; }
        @media (min-width: 576px) { /* o card é estreito: a grade do feriado não segue os breakpoints da tela */
            .ob-page .feriado-item .row > .col-sm-4 { width: 38%; }
            .ob-page .feriado-item .row > .col-sm-8 { width: 62%; }
            .ob-page .feriado-item .row > .col-lg-4 { width: 100%; min-height: 0 !important; }
        }
        .ob-page .cfg-actions { justify-content: space-between; }
        .ob-page .cfg-actions .ob-btn-next { margin-left: auto; }

        /* Passos da etapa Agenda: uma pergunta por tela */
        .ob-progress { margin-bottom: 1.25rem; }
        .ob-progress-text { display: flex; justify-content: space-between; gap: 1rem; margin-bottom: .4rem; color: var(--dash-muted); font-weight: 600; font-size: .9rem; }
        .ob-progress-bar { height: 6px; border-radius: 6px; background: #e8edf2; overflow: hidden; }
        .ob-progress-bar > span { display: block; height: 100%; width: 25%; border-radius: 6px; background: var(--dash-primary); transition: width .3s ease-out; }
        .ob-substep { display: none; }
        .ob-substep.active { display: block; animation: ob-step-in .3s ease-out; }
        .ob-substep-title:focus { outline: none; }
        .ob-page .cfg-choices:not(.is-cards) { display: grid; grid-template-columns: repeat(auto-fill, minmax(88px, 1fr)); }
        .ob-page .cfg-choices:not(.is-cards) .cfg-choice { display: flex; align-items: center; justify-content: center; min-height: 52px; padding: .5rem .1rem; text-align: center; }

        /* Prévia dos horários (duração + intervalo) */
        .ob-preview { padding: 1rem 1.1rem; border-radius: 16px; background: var(--dash-primary-soft); }
        .ob-preview-title { display: block; color: var(--dash-ink); font-weight: 600; }
        .ob-preview-title i { color: var(--dash-primary); }
        .ob-preview-times { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .65rem; }
        .ob-preview-times span { padding: .3rem .7rem; border-radius: 10px; background: #fff; color: var(--dash-primary); font-weight: 700; }

        /* Modalidade em cartões */
        .ob-page .cfg-choices.is-cards { display: grid; gap: .65rem; }
        .ob-page .is-cards .cfg-choice { display: flex; align-items: center; gap: .9rem; padding: 1rem 1.1rem; border-color: var(--dash-line); border-radius: 16px; text-align: left; }
        .ob-page .is-cards .btn-check:checked + .cfg-choice { background: var(--dash-primary-soft); border-color: var(--dash-primary); color: var(--dash-ink); box-shadow: inset 0 0 0 1px var(--dash-primary); }
        .cfg-choice-icon { width: 44px; height: 44px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 12px; background: #f2f5f8; color: var(--dash-primary); font-size: 1.1rem; }
        .btn-check:checked + .cfg-choice .cfg-choice-icon { background: #fff; }
        .cfg-choice-body { flex: 1; min-width: 0; }
        .cfg-choice-title { display: block; font-size: 1.05rem; }
        .cfg-choice-text { display: block; color: var(--dash-muted); font-weight: 400; font-size: .92rem; }
        .cfg-choice-check { color: var(--dash-primary); font-size: 1.25rem; visibility: hidden; }
        .btn-check:checked + .cfg-choice .cfg-choice-check { visibility: visible; }

        .ob-copy-first { margin-bottom: .85rem; }

        /* Ajustes opcionais (pausas e feriados) */
        .ob-optional { border: 1px solid var(--dash-line); border-radius: 16px; background: #fff; }
        .ob-optional-toggle { display: flex; align-items: center; gap: 1rem; width: 100%; padding: 1rem 1.1rem; border: 0; background: transparent; text-align: left; border-radius: 16px; }
        .ob-optional-toggle:hover { background: #f7f9fb; }
        .ob-optional-toggle .cfg-row-icon { width: 42px; height: 42px; font-size: 1.1rem; border-radius: 12px; }
        .ob-optional-chevron { color: var(--dash-muted); transition: transform .2s; }
        .ob-optional-toggle[aria-expanded="true"] .ob-optional-chevron { transform: rotate(180deg); }
        .ob-optional-body { padding: 0 1.1rem 1.1rem; }

        /* Link da página e etapa concluída */
        .ob-link-preview { display: flex; align-items: center; gap: .5rem; margin-top: .6rem; padding: .6rem .85rem; border-radius: 12px; background: var(--dash-primary-soft); color: var(--dash-primary); font-weight: 600; word-break: break-all; font-size: 1.2rem;  }
        .ob-done { text-align: center; padding: 1.5rem 0 .5rem; }
        .ob-done-icon { width: 84px; height: 84px; margin: 0 auto 1.25rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.4rem; background: #e4f6ec; color: #1c8a56; animation: ob-pop .45s cubic-bezier(.2, 1.4, .4, 1); }
        @keyframes ob-pop { from { transform: scale(.4); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .ob-done-link { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: .75rem; margin: 1.5rem auto; padding: 1rem; max-width: 520px; border: 1px dashed #cfd9e2; border-radius: 16px; background: #fbfcfd; }
        .ob-done-link a { font-weight: 700; color: var(--dash-primary); word-break: break-all; }
        .dash-btn-soft{font-size: 1.2rem;}

        /* Ajuda */
        .ob-help { position: fixed; right: 16px; bottom: 16px; z-index: 1050; display: none; align-items: center; gap: .5rem; background: #fff; border-radius: 40px; padding: .5rem .5rem .5rem 1rem; box-shadow: 0 8px 24px rgba(0,0,0,.18); animation: ob-help-in .4s ease-out; }
        .ob-help.show { display: flex; }
        .ob-help a.ob-help-link { font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: .5rem; color: #1e4f5f; }
        .ob-help .ob-help-close { border: 0; background: transparent; width: 28px; height: 28px; border-radius: 50%; color: #6c7a84; }
        @keyframes ob-help-in { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 575.98px) {
            .ob-wrapper { padding: 16px 12px 120px; }
            .ob-steps-label { font-size: .8rem; text-align: center; }
            .ob-logout { position: static; text-align: right; margin-bottom: .25rem; }
            .ob-help { bottom: 96px; } /* acima da barra de ações fixa */
            .ob-page .cfg-actions .btn { min-width: 0; }
            .ob-page .cfg-actions { padding-bottom: calc(.85rem + env(safe-area-inset-bottom)); }
            .ob-page .ob-copy-first { width: 100%; }
            /* Período em duas linhas: início/fim (e remover) em cima, formato embaixo — sem rolagem lateral em 360px */
            .ob-page .cfg-period:not([hidden]) { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; }
            .ob-page .cfg-period .cfg-day-mode, .ob-page .cfg-period .invalid-feedback { grid-column: 1 / -1; width: 100%; }
            /* Período extra: o remover fica ao lado do formato, sem apertar os horários */
            .ob-page .cfg-period.is-extra .cfg-day-mode { grid-column: 1 / 3; }
            .ob-page .cfg-period .cfg-period-remove { grid-column: 3; grid-row: 2; justify-self: end; }
            .ob-page .cfg-head-icon { display: none; }
            .ob-slug-prefix { display: none; } /* o link completo aparece logo abaixo */
            .ob-slug-group > .form-control { border-top-left-radius: 12px !important; border-bottom-left-radius: 12px !important; }
        }
    </style>

    <div class="ob-wrapper" data-step="{{ $step }}">
        <div class="ob-page">
            <div class="ob-top">
                <form method="POST" action="{{ route('logout') }}" class="ob-logout">
                    @csrf
                    <button type="submit" class="btn btn-link btn-sm"><i class="fa-solid fa-arrow-right-from-bracket me-1"></i>Sair</button>
                </form>
                <div class="d-flex justify-content-center" aria-label="{{ config('app.name') }}">
                    <x-logo-psi4 :size="48" text="#1b323c" accent="#2fa595" />
                </div>
                <ol class="ob-steps" id="ob-steps" aria-label="Etapas da configuração">
                    @foreach ($steps as $number => $label)
                        <li class="ob-steps-item" data-dot="{{ $number }}">
                            <span class="ob-steps-dot"><span class="ob-steps-num">{{ $number }}</span><i class="fa-solid fa-check"></i></span>
                            <span class="ob-steps-label">{{ $label }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="dash-card cfg-card">
                {{-- Etapa 1: agenda --}}
                <section class="ob-step" id="step-agenda" data-step="1">
                    {{-- Uma pergunta por tela (onboarding.js); tudo fica no mesmo form e é salvo só no último passo --}}
                    <form id="form-onboarding-agenda" novalidate data-saved="{{ $agendaSettings ? 1 : 0 }}" data-default-hours="{{ json_encode($defaultHours) }}">
                        @csrf
                        <div class="ob-progress">
                            <div class="ob-progress-text">
                                <span id="ob-progress-label">Agenda</span>
                                <span id="ob-progress-count"></span>
                            </div>
                            <div class="ob-progress-bar" id="ob-progress-bar" role="progressbar" aria-label="Passos da agenda" aria-valuemin="1" aria-valuemax="{{ count($agendaSubsteps) }}" aria-valuenow="1">
                                <span id="ob-progress-fill"></span>
                            </div>
                        </div>

                        {{-- Passo 1: duração --}}
                        <div class="ob-substep" data-substep="1" data-label="{{ $agendaSubsteps[1] }}">
                            <div class="cfg-head">
                                <div class="cfg-head-icon"><i class="fa-regular fa-hand"></i></div>
                                <div class="cfg-head-body">
                                    <h2 class="cfg-head-title ob-substep-title" tabindex="-1">Bem-vindo(a), {{ $firstName }}!</h2>
                                    <p class="cfg-head-sub">Vamos montar sua agenda em {{ count($agendaSubsteps) }} passos rápidos. Já deixamos tudo preenchido, ajuste só o que precisar. Você pode mudar depois em Configurações.</p>
                                </div>
                            </div>

                            <section class="cfg-section">
                                <h3 id="consultationTimeLabel" class="cfg-section-title">Duração da consulta</h3>
                                <small class="cfg-section-sub mb-3">Quanto tempo dura cada sessão com o paciente.</small>
                                @include('settings.partials.duration-options', [
                                    'id' => 'consultationTime',
                                    'name' => 'consultation_time',
                                    'value' => substr($agendaSettings?->consultation_time ?? '00:50', 0, 5),
                                    'options' => [20, 30, 40, 45, 50, 60, 75, 90, 120],
                                ])
                            </section>
                        </div>

                        {{-- Passo 2: intervalo --}}
                        <div class="ob-substep" data-substep="2" data-label="{{ $agendaSubsteps[2] }}">
                            <div class="cfg-head">
                                <div class="cfg-head-icon"><i class="fa-regular fa-hourglass-half"></i></div>
                                <div class="cfg-head-body">
                                    <h2 id="availableTimeMinutesLabel" class="cfg-head-title ob-substep-title" tabindex="-1">Intervalo entre consultas</h2>
                                    <p class="cfg-head-sub">Tempo livre entre o fim de uma consulta e o início da próxima.</p>
                                </div>
                            </div>

                            <section class="cfg-section">
                                @include('settings.partials.duration-options', [
                                    'id' => 'availableTimeMinutes',
                                    'name' => 'available_time_minutes',
                                    'value' => substr($agendaSettings?->available_time_minutes ?? '00:10', 0, 5),
                                    'options' => [0, 5, 10, 15, 20, 30, 45, 60],
                                    'zeroLabel' => 'Sem intervalo',
                                ])
                            </section>

                            <section class="cfg-section ob-preview" aria-live="polite">
                                <span class="ob-preview-title"><i class="fa-regular fa-clock me-2"></i><span id="ob-preview-text"></span></span>
                                <div class="ob-preview-times" id="ob-preview-times"></div>
                            </section>
                        </div>

                        {{-- Passo 3: modalidade --}}
                        <div class="ob-substep" data-substep="3" data-label="{{ $agendaSubsteps[3] }}">
                            <div class="cfg-head">
                                <div class="cfg-head-icon"><i class="fa-solid fa-user-doctor"></i></div>
                                <div class="cfg-head-body">
                                    <h2 class="cfg-head-title ob-substep-title" tabindex="-1">Como você atende?</h2>
                                    <p class="cfg-head-sub">Define o que o paciente vê ao agendar: link da chamada ou endereço do consultório.</p>
                                </div>
                            </div>

                            <section class="cfg-section">
                                @include('settings.partials.attendance-mode', ['askAddressNextStep' => true, 'cards' => true])
                            </section>
                        </div>

                        {{-- Passo 4: dias e horários --}}
                        <div class="ob-substep" data-substep="4" data-label="{{ $agendaSubsteps[4] }}">
                            <div class="cfg-head">
                                <div class="cfg-head-icon"><i class="fa-regular fa-calendar-days"></i></div>
                                <div class="cfg-head-body">
                                    <h2 class="cfg-head-title ob-substep-title" tabindex="-1">Dias e horários de atendimento</h2>
                                    <p class="cfg-head-sub">Sugerimos horários conforme sua forma de atendimento; ajuste como preferir.</p>
                                </div>
                            </div>

                            <section class="cfg-section">
                                <button type="button" class="dash-btn-soft ob-copy-first d-none" id="btn-copy-first-day">
                                    <i class="fa-regular fa-copy me-1"></i>Repetir o horário de <span id="ob-copy-first-label"></span> nos outros dias
                                </button>

                                <div class="cfg-slots">
                                    @foreach ($weekDays as $key => $default)
                                        @php
                                            $saved = is_array($savedDays) ? ($savedDays[$key] ?? null) : null;
                                            $dayEnabled = $saved !== null ? filter_var($saved['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN) : $default['enabled'];
                                        @endphp
                                        <div class="cfg-slot cfg-day {{ $dayEnabled ? '' : 'is-off' }}">
                                            <div class="form-check form-switch cfg-slot-toggle">
                                                <input class="form-check-input day-toggle" type="checkbox" role="switch" id="day-{{ $key }}" name="agenda_days[{{ $key }}][enabled]" value="1" @checked($dayEnabled)>
                                                <label class="cfg-slot-label" for="day-{{ $key }}">{{ $default['label'] }}</label>
                                            </div>
                                            @include('settings.partials.day-periods', ['key' => $key, 'saved' => $saved, 'label' => $default['label'], 'start' => $defaultHours['online'][0]['start'], 'end' => $defaultHours['online'][0]['end']])
                                            <div class="cfg-slot-end">
                                                <span class="dash-chip dash-chip-muted cfg-slot-off">Sem atendimento</span>
                                                <button type="button" class="dash-round-btn dash-round-muted cfg-slot-copy" title="Copiar este horário para os outros dias ativos" aria-label="Copiar horário de {{ $default['label'] }} para os outros dias ativos">
                                                    <i class="fa-regular fa-copy"></i>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>

                            <section class="cfg-section ob-optional">
                                <button type="button" class="ob-optional-toggle" data-bs-toggle="collapse" data-bs-target="#ob-optional-body" aria-expanded="false" aria-controls="ob-optional-body">
                                    <span class="cfg-row-icon"><i class="fa-solid fa-sliders"></i></span>
                                    <span class="cfg-row-body">
                                        <span class="cfg-row-title">Pausas e feriados <span class="dash-chip dash-chip-muted">Opcional</span></span>
                                        <span class="cfg-row-detail" id="ob-optional-summary"></span>
                                    </span>
                                    <i class="fa-solid fa-chevron-down ob-optional-chevron"></i>
                                </button>

                                <div class="collapse" id="ob-optional-body">
                                    <div class="ob-optional-body">
                                        <div class="cfg-section-head">
                                            <div>
                                                <h4 class="cfg-section-title">Pausas</h4>
                                                <small class="cfg-section-sub">Intervalos bloqueados todos os dias na agenda.</small>
                                            </div>
                                        </div>
                                        <div class="cfg-slots">
                                            @foreach ($meals as $meal)
                                                <div class="cfg-slot cfg-meal {{ $meal['enabled'] ? '' : 'is-off' }}">
                                                    <div class="form-check form-switch cfg-slot-toggle">
                                                        <input class="form-check-input meal-toggle" type="checkbox" role="switch" id="{{ $meal['switch'] }}" name="{{ $meal['key'] }}_enabled" value="1" data-target="#{{ $meal['fields'] }}" data-label="{{ $meal['label'] }}" @checked($meal['enabled'])>
                                                        <label class="cfg-slot-label" for="{{ $meal['switch'] }}"><i class="{{ $meal['icon'] }}"></i>{{ $meal['label'] }}</label>
                                                    </div>
                                                    <div class="cfg-slot-times" id="{{ $meal['fields'] }}">
                                                        <input type="time" class="form-control cfg-time" name="{{ $meal['key'] }}_start" value="{{ substr($meal['start'], 0, 5) }}" aria-label="Início {{ $meal['label'] }}">
                                                        <span>até</span>
                                                        <input type="time" class="form-control cfg-time" name="{{ $meal['key'] }}_end" value="{{ substr($meal['end'], 0, 5) }}" aria-label="Fim {{ $meal['label'] }}">
                                                    </div>
                                                    <div class="cfg-slot-end">
                                                        <span class="dash-chip dash-chip-muted cfg-slot-off">Desativado</span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="cfg-section-head mt-4">
                                            <div>
                                                <h4 class="cfg-section-title">Feriados</h4>
                                                <small class="cfg-section-sub">Por padrão você não atende nos feriados. Ative "Atender neste dia" para definir um horário especial.</small>
                                            </div>
                                            <div class="d-flex flex-wrap gap-2">
                                                <button type="button" class="dash-btn-soft" id="btn-import-holidays">
                                                    <i class="fa-solid fa-flag me-1"></i>Importar feriados nacionais
                                                </button>
                                                <button type="button" class="dash-btn-soft" id="btn-add-holiday" onclick="addFeriado()">
                                                    <i class="fa-solid fa-plus me-1"></i>Adicionar
                                                </button>
                                            </div>
                                        </div>

                                        <div id="container-feriados" class="cfg-items">
                                            @foreach ($holidays as $holiday)
                                                @include('settings.partials.holiday-item', [
                                                    'id' => $holiday->id,
                                                    'date' => \Carbon\Carbon::parse($holiday->date)->format('d/m/Y'),
                                                    'name' => $holiday->name,
                                                    'work' => (bool) $holiday->work_enabled,
                                                    'start' => substr($holiday->start ?? '08:00', 0, 5),
                                                    'end' => substr($holiday->end ?? '12:00', 0, 5),
                                                ])
                                            @endforeach
                                        </div>

                                        <div id="feriados-empty" class="dash-empty {{ $holidays->isEmpty() ? '' : 'd-none' }}">
                                            <i class="fa-regular fa-calendar-check"></i>
                                            Nenhum feriado cadastrado.
                                            <small class="d-block mt-1">Importe os feriados nacionais ou adicione uma data manualmente.</small>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div class="cfg-actions">
                            <button type="button" class="btn btn-outline-secondary d-none" id="btn-onboarding-agenda-back"><i class="fa-solid fa-arrow-left me-2"></i>Voltar</button>
                            <button type="submit" class="btn btn-primary ob-btn-next">Continuar<i class="fa-solid fa-arrow-right ms-2"></i></button>
                        </div>
                    </form>
                </section>

                {{-- Etapa 2: dados do psicólogo --}}
                <section class="ob-step" id="step-psychologist" data-step="2">
                    <form id="form-onboarding-psychologist" novalidate>
                        @csrf
                        <div class="cfg-head">
                            <div class="cfg-head-icon"><i class="fa-regular fa-id-badge"></i></div>
                            <div class="cfg-head-body">
                                <h2 class="cfg-head-title">Seus dados profissionais</h2>
                                <p class="cfg-head-sub">Aparecem nos seus documentos e na sua página de agendamento.</p>
                            </div>
                        </div>

                        <section class="cfg-section">
                            <div class="row g-4 align-items-center">
                                <div class="col-md-5">
                                    <div class="cfg-photo text-center">
                                        <span class="form-label d-block">Sua foto <small class="text-muted fw-normal">(opcional)</small></span>
                                        <div class="photo-cropper">
                                            <div class="photo-canvas-wrap mx-auto" id="photoCanvasWrap">
                                                <canvas id="photoCanvas" width="512" height="512" aria-label="Pré-visualização da foto"></canvas>
                                                <div class="crop-guide" aria-hidden="true"></div>
                                                <div class="photo-change-overlay" aria-hidden="true"><i class="fas fa-camera"></i></div>
                                            </div>
                                            <input id="fotoPsicologo" name="foto_psicologo" type="file" class="d-none" accept="image/jpeg,image/png,image/webp">
                                            <input id="fotoPsicologoCortada" name="foto_psicologo_cortada" type="hidden">
                                            <div id="photoZoomWrap" class="d-none mx-auto" style="max-width: 280px;">
                                                <label for="photoZoom" class="form-label small text-muted mt-3 mb-1">Zoom da foto</label>
                                                <input id="photoZoom" type="range" class="form-range" min="1" max="3" step="0.01" value="1" disabled>
                                                <small class="cfg-hint">Arraste a foto para ajustar o enquadramento.</small>
                                            </div>
                                            <small class="cfg-hint" id="photoHint">Toque no círculo para escolher uma foto.</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-7">
                                    <div class="mb-3">
                                        <label for="nomeClinica" class="form-label">Seu nome profissional <span class="text-danger">*</span></label>
                                        <input id="nomeClinica" name="nome_clinica" type="text" class="form-control" maxlength="255" value="{{ $clinicName }}" required>
                                        <small class="cfg-hint">Como você quer ser chamado(a) pelos pacientes.</small>
                                    </div>
                                    <div>
                                        <label for="crp" class="form-label">CRP <span class="text-danger">*</span></label>
                                        <input id="crp" name="crp" type="tel" class="form-control crp" inputmode="numeric" placeholder="00/000000" value="{{ maskValue($crp, '##/######') }}" required>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="cfg-section">
                            <h3 class="cfg-section-title mb-1">Seus dados de contato</h3>
                            <small class="cfg-section-sub d-block mb-3">Ficam na sua conta e nunca têm ligação com o conteúdo dos prontuários.</small>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="document" class="form-label">CPF <span class="text-danger">*</span></label>
                                    <input id="document" name="document" type="tel" class="form-control cpf" inputmode="numeric" placeholder="___.___.___-__"
                                           value="{{ maskValue($user->document, '###.###.###-##') }}" @if ($user->document) readonly aria-describedby="document-hint" @endif required>
                                    <small class="cfg-hint" id="document-hint">
                                        @if ($user->document)
                                            O CPF não pode ser alterado. <a href="{{ route('help.index') }}">Fale com o suporte</a>
                                        @else
                                            Usado nos seus recibos do Receita Saúde e na cobrança da assinatura.
                                        @endif
                                    </small>
                                </div>
                                <div class="col-md-6">
                                    <label for="whatsapp" class="form-label">WhatsApp <span class="text-danger">*</span></label>
                                    <input id="whatsapp" name="whatsapp" type="tel" class="form-control whatsapp" inputmode="tel" autocomplete="tel-national" placeholder="(__) _____-____"
                                           value="{{ $user->whatsapp ? formatPhone($user->whatsapp) : '' }}" required>
                                    <small class="cfg-hint">Aparece na sua página de agendamento e nos documentos, e é por onde o suporte fala com você.</small>
                                </div>
                            </div>
                        </section>

                        {{-- Só para quem atende presencialmente (escolha da etapa 1); o JS mostra/esconde ao trocar a modalidade --}}
                        <section class="cfg-section @unless ($inPerson) d-none @endunless" id="ob-address">
                            <h3 class="cfg-section-title mb-1">Endereço do consultório</h3>
                            <small class="cfg-section-sub d-block mb-3">Aparece na sua página de agendamento para quem marcar sessão presencial.</small>
                            @include('settings.partials.address', ['required' => true])
                        </section>

                        <div class="cfg-actions">
                            <button type="button" class="btn btn-outline-secondary" id="btn-onboarding-back"><i class="fa-solid fa-arrow-left me-2"></i>Voltar</button>
                            <button type="submit" class="btn btn-primary ob-btn-next">Continuar<i class="fa-solid fa-arrow-right ms-2"></i></button>
                        </div>
                    </form>
                </section>

                {{-- Etapa 3: agendamento online --}}
                <section class="ob-step" id="step-booking" data-step="3">
                    <form id="form-onboarding-booking" novalidate>
                        @csrf
                        <div class="cfg-head">
                            <div class="cfg-head-icon"><i class="fa-solid fa-link"></i></div>
                            <div class="cfg-head-body">
                                <h2 class="cfg-head-title">Agendamento online</h2>
                                <p class="cfg-head-sub">Seus pacientes escolhem serviço, dia e horário sozinhos, conforme os dias de atendimento que você definiu.</p>
                            </div>
                        </div>

                        <section class="cfg-section">
                            <label for="slug" class="form-label">Endereço da sua página <span class="text-danger">*</span></label>
                            <div class="input-group ob-slug-group">
                                <span class="input-group-text ob-slug-prefix input-lp">{{ url('/lp') }}/</span>
                                <input id="slug" name="slug" type="text" class="form-control" maxlength="60" placeholder="seu-nome" value="{{ $suggestedSlug }}" required>
                                <button id="btn-check-slug" type="button" class="btn btn-outline-secondary" title="Verificar disponibilidade" aria-label="Verificar disponibilidade do endereço">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <div id="slug-availability" class="small mt-1 d-none" role="status"></div>
                            <small class="cfg-hint">Somente letras minúsculas, números e hífen.</small>
                            <div class="ob-link-preview" aria-live="polite">
                                <i class="fa-solid fa-globe"></i>
                                <span id="ob-link-preview" data-base="{{ url('/lp') }}/"></span>
                            </div>
                        </section>

                        <section class="cfg-section">
                            <div class="cfg-section-head">
                                <div>
                                    <h3 class="cfg-section-title">Serviços e valores <span class="text-danger">*</span></h3>
                                    <small class="cfg-section-sub">Ex.: Terapia individual R$ 150,00. Use o mesmo valor em todos se preferir.</small>
                                </div>
                                <button type="button" class="dash-btn-soft" id="btn-add-service" onclick="addServico()">
                                    <i class="fa-solid fa-plus me-1"></i>Adicionar serviço
                                </button>
                            </div>

                            <div id="container-servicos" class="cfg-items">
                                @forelse($services as $i => $service)
                                    @include('settings.partials.service-item', ['index' => $i, 'service' => $service])
                                @empty
                                    @include('settings.partials.service-item', ['index' => 0, 'service' => null])
                                @endforelse
                            </div>
                        </section>

                        <div class="cfg-actions">
                            <button type="button" class="btn btn-outline-secondary" id="btn-onboarding-back-booking"><i class="fa-solid fa-arrow-left me-2"></i>Voltar</button>
                            <button type="submit" class="btn btn-primary ob-btn-next"><i class="fa-solid fa-check me-2"></i>Concluir</button>
                        </div>
                    </form>
                </section>

                {{-- Concluído --}}
                <section class="ob-step" id="step-done" data-step="4">
                    <div class="ob-done">
                        <div class="ob-done-icon"><i class="fa-solid fa-check"></i></div>
                        <h2 class="cfg-head-title">Tudo pronto, {{ $firstName }}!</h2>
                        <p class="cfg-head-sub">Sua agenda está configurada. Compartilhe o link abaixo com seus pacientes para que eles agendem sozinhos.</p>

                        <div class="ob-done-link">
                            <a id="ob-done-url" href="#" target="_blank" rel="noopener"></a>
                            <button type="button" class="dash-btn-soft" id="btn-copy-booking-link">
                                <i class="fa-regular fa-copy me-1"></i>Copiar link
                            </button>
                        </div>

                        <a id="ob-done-dashboard" href="{{ route('dashboard') }}" class="btn btn-primary btn-lg px-5">Ir para o painel<i class="fa-solid fa-arrow-right ms-2"></i></a>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <div class="ob-help" id="ob-help" role="complementary" aria-label="Ajuda">
        <a class="ob-help-link" href="{{ generateLinkWhatsApp(__('messages.text_help', ['code' => $user->code])) }}" target="_blank" rel="noopener">
            @include('components.iacon-whatsapp')
            Precisa de ajuda?
        </a>
        <button type="button" class="ob-help-close" id="ob-help-close" aria-label="Fechar">&times;</button>
    </div>

    <template id="tpl-feriado">
        @include('settings.partials.holiday-item', ['id' => '__ID__'])
    </template>
    <template id="tpl-periodo">
        @include('settings.partials.day-period', ['key' => '__DAY__', 'index' => '__INDEX__', 'period' => null, 'label' => '__LABEL__', 'extra' => true])
    </template>
    <template id="tpl-servico">
        @include('settings.partials.service-item', ['index' => '__INDEX__', 'service' => null])
    </template>
@endsection

@section('footer')
    <script>
        window.onboardingPhotoUrl = @json($psychologistSettings?->photoUrl());
    </script>
    <script src="{{ mix('js/onboarding.js') }}"></script>
@endsection
