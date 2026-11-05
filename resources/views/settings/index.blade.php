@extends('layouts.app')
@section('title', 'Configurações')

@section('header')
    @include('components.css-dash')
    @include('settings.partials.settings-css')
@endsection

@section('content')
    @php
        $chipClass = fn ($status) => match ($status->color()) {
            'text-success' => 'dash-chip-success',
            'text-danger' => 'dash-chip-danger',
            'text-warning' => 'dash-chip-warning',
            default => 'dash-chip-info',
        };

        $googleStatus = $googleCalendar?->status;
        $googleConnected = $googleStatus?->value === \App\Enums\GoogleCalendarStatusEnum::INTEGRATED->value;
        $googleNeedsReauth = $googleStatus?->value === \App\Enums\GoogleCalendarStatusEnum::NEEDS_REAUTH->value;
        $paymentStatus = $paymentAccount?->status;
        $paymentConnected = $paymentStatus?->value === \App\Enums\PaymentAccountsStatusEnum::INTEGRATED->value;
        $bookingEnabled = (bool) $bookingSettings?->public_booking_enabled;
        $twoFactorEnabled = auth()->user()->hasTwoFactorEnabled();
        $slug = auth()->user()->slug;

        $weekDays = [
            'seg' => ['label' => 'Segunda-feira', 'enabled' => true, 'start' => '08:00', 'end' => '18:00'],
            'ter' => ['label' => 'Terça-feira', 'enabled' => true, 'start' => '08:00', 'end' => '18:00'],
            'qua' => ['label' => 'Quarta-feira', 'enabled' => true, 'start' => '08:00', 'end' => '18:00'],
            'qui' => ['label' => 'Quinta-feira', 'enabled' => true, 'start' => '08:00', 'end' => '18:00'],
            'sex' => ['label' => 'Sexta-feira', 'enabled' => true, 'start' => '08:00', 'end' => '17:00'],
            'sab' => ['label' => 'Sábado', 'enabled' => false, 'start' => '08:00', 'end' => '12:00'],
            'dom' => ['label' => 'Domingo', 'enabled' => false, 'start' => '08:00', 'end' => '12:00'],
        ];
        $savedDays = $agendaSettings?->agenda_days;

        $meals = [
            ['key' => 'lunch', 'switch' => 'almocoSwitch', 'fields' => 'almocoFields', 'icon' => 'fa-solid fa-utensils', 'label' => 'Almoço', 'enabled' => $agendaSettings?->lunch_enabled ?? true, 'start' => $agendaSettings?->lunch_start ?? '12:00', 'end' => $agendaSettings?->lunch_end ?? '13:00'],
            ['key' => 'dinner', 'switch' => 'jantaSwitch', 'fields' => 'jantaFields', 'icon' => 'fa-solid fa-moon', 'label' => 'Janta', 'enabled' => $agendaSettings?->dinner_enabled ?? false, 'start' => $agendaSettings?->dinner_start ?? '19:00', 'end' => $agendaSettings?->dinner_end ?? '20:00'],
        ];

        $upcomingHolidays = ($agendaSettings?->holidays ?? collect())
            ->filter(fn ($holiday) => \Carbon\Carbon::parse($holiday->date)->gte(today()));
    @endphp

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            @include('components.icon-settings', ['css' => 'me-2']) Configurações
        </h3>
    </div>

    <div class="row g-4 cfg-page" id="settings-page"
         data-photo-url="{{ $psychologistSettings?->photoUrl() }}"
         data-scope-help-gif="{{ asset('imgs/google-calendar/autorizar-permissoes.gif') }}">

        <div class="col-12 col-lg-3">
            <nav class="dash-card cfg-nav nav" id="settingsNav" role="tablist" aria-orientation="vertical" aria-label="Seções de configuração">
                <div class="cfg-nav-group" role="presentation">Agenda</div>
                <div class="cfg-nav-items" id="agendaTabs" role="presentation">
                    <button class="nav-link cfg-nav-link active" id="horarios-tab" data-bs-toggle="tab" data-bs-target="#horarios" data-form="#form-store-agenda" type="button" role="tab" aria-controls="horarios" aria-selected="true">
                        <i class="fa-regular fa-clock"></i><span class="cfg-nav-label">Dias de atendimento</span><span class="cfg-nav-dirty" title="Alterações não salvas">●</span>
                    </button>
                    <button class="nav-link cfg-nav-link" id="feriados-tab" data-bs-toggle="tab" data-bs-target="#feriados" data-form="#form-store-agenda" type="button" role="tab" aria-controls="feriados" aria-selected="false">
                        <i class="fa-regular fa-calendar-xmark"></i><span class="cfg-nav-label">Feriados e exceções</span><span class="cfg-nav-dirty" title="Alterações não salvas">●</span>
                    </button>
                    <button class="nav-link cfg-nav-link" id="agendamento-online-tab" data-bs-toggle="tab" data-bs-target="#agendamento-online" data-form="#form-store-booking" type="button" role="tab" aria-controls="agendamento-online" aria-selected="false">
                        <i class="fa-solid fa-link"></i><span class="cfg-nav-label">Agendamento online</span><span class="cfg-nav-dirty" title="Alterações não salvas">●</span>
                        <span id="nav-status-booking" class="cfg-nav-status {{ $bookingEnabled ? 'is-on' : '' }}" title="{{ $bookingEnabled ? 'Página ativa' : 'Página inativa' }}"></span>
                    </button>
                    <button class="nav-link cfg-nav-link" id="google-calendar-tab" data-bs-toggle="tab" data-bs-target="#google-calendar" data-form="#form-update-google-calendar" type="button" role="tab" aria-controls="google-calendar" aria-selected="false">
                        <i class="fa-brands fa-google"></i><span class="cfg-nav-label">Google Agenda</span><span class="cfg-nav-dirty" title="Alterações não salvas">●</span>
                        <span class="cfg-nav-status {{ $googleConnected ? 'is-on' : ($googleNeedsReauth ? 'is-warning' : '') }}" title="{{ $googleStatus?->getText() ?? 'Não conectado' }}"></span>
                    </button>
                </div>

                <div class="cfg-nav-group" role="presentation">Conta</div>
                <div class="cfg-nav-items" id="configParentTabs" role="presentation">
                    <button class="nav-link cfg-nav-link" id="psicologo-parent-tab" data-bs-toggle="tab" data-bs-target="#psicologo-parent" data-form="#form-store-psychologist" type="button" role="tab" aria-controls="psicologo-parent" aria-selected="false">
                        <i class="fa-regular fa-id-card"></i><span class="cfg-nav-label">Psicólogo</span><span class="cfg-nav-dirty" title="Alterações não salvas">●</span>
                    </button>
                    <button class="nav-link cfg-nav-link" id="pagamentos-parent-tab" data-bs-toggle="tab" data-bs-target="#form-store-payment-account" type="button" role="tab" aria-controls="form-store-payment-account" aria-selected="false">
                        <i class="fa-regular fa-credit-card"></i><span class="cfg-nav-label">Pagamentos</span>
                        <span class="cfg-nav-status {{ $paymentConnected ? 'is-on' : '' }}" title="{{ $paymentStatus?->getText() ?? 'Não conectado' }}"></span>
                    </button>
                    <button class="nav-link cfg-nav-link" id="seguranca-parent-tab" data-bs-toggle="tab" data-bs-target="#seguranca-parent" type="button" role="tab" aria-controls="seguranca-parent" aria-selected="false">
                        <i class="fa-solid fa-shield-halved"></i><span class="cfg-nav-label">Segurança</span>
                        <span id="nav-status-two-factor" class="cfg-nav-status {{ $twoFactorEnabled ? 'is-on' : '' }}" title="{{ $twoFactorEnabled ? '2 fatores ativo' : '2 fatores inativo' }}"></span>
                    </button>
                </div>
            </nav>
        </div>

        <div class="col-12 col-lg-9">
            <div class="tab-content cfg-content" id="settingsTabsContent">

                {{-- Dias de atendimento e Feriados compartilham o mesmo formulário (mesmo endpoint) --}}
                <form id="form-store-agenda" class="needs-validation" novalidate>
                    @csrf

                    <div class="tab-pane fade show active" id="horarios" role="tabpanel" aria-labelledby="horarios-tab">
                        <div class="dash-card cfg-card">
                            <div class="cfg-head">
                                <div class="cfg-head-icon"><i class="fa-regular fa-clock"></i></div>
                                <div class="cfg-head-body">
                                    <h4 class="cfg-head-title">Dias de atendimento</h4>
                                    <p class="cfg-head-sub">Defina quando você atende. Esses horários aparecem como livres na agenda e na página de agendamento online.</p>
                                </div>
                            </div>

                            <section class="cfg-section">
                                <div class="mb-4">
                                    <span id="consultationTimeLabel" class="form-label d-block">Duração da consulta</span>
                                    @include('settings.partials.duration-options', [
                                        'id' => 'consultationTime',
                                        'name' => 'consultation_time',
                                        'value' => substr($agendaSettings?->consultation_time ?? '00:50', 0, 5),
                                        'options' => [20, 30, 40, 45, 50, 60, 75, 90, 120],
                                    ])
                                </div>
                                <div>
                                    <span id="availableTimeMinutesLabel" class="form-label d-block">Intervalo entre consultas</span>
                                    @include('settings.partials.duration-options', [
                                        'id' => 'availableTimeMinutes',
                                        'name' => 'available_time_minutes',
                                        'value' => substr($agendaSettings?->available_time_minutes ?? '00:10', 0, 5),
                                        'options' => [0, 5, 10, 15, 20, 30, 45, 60],
                                        'zeroLabel' => 'Sem intervalo',
                                    ])
                                    <small class="cfg-hint">Tempo livre entre o fim de uma consulta e o início da próxima.</small>
                                </div>
                            </section>

                            <section class="cfg-section">
                                @include('settings.partials.attendance-mode')
                            </section>

                            <section class="cfg-section">
                                <div class="cfg-section-head">
                                    <div>
                                        <h5 class="cfg-section-title">Dias da semana</h5>
                                        <small class="cfg-section-sub">Use <i class="fa-regular fa-copy"></i> para copiar o horário de um dia para os demais dias ativos.</small>
                                    </div>
                                </div>

                                <div class="cfg-slots">
                                    @foreach ($weekDays as $key => $default)
                                        @php
                                            $saved = is_array($savedDays) ? ($savedDays[$key] ?? null) : null;
                                            $dayEnabled = $saved !== null ? filter_var($saved['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN) : $default['enabled'];
                                        @endphp
                                        <div class="cfg-slot cfg-day {{ $dayEnabled ? '' : 'is-off' }}">
                                            <div class="form-check form-switch cfg-slot-toggle">
                                                <input class="form-check-input day-toggle" type="checkbox" role="switch" id="{{ $key }}" name="agenda_days[{{ $key }}][enabled]" value="1" @checked($dayEnabled)>
                                                <label class="cfg-slot-label" for="{{ $key }}">{{ $default['label'] }}</label>
                                            </div>
                                            @include('settings.partials.day-periods', ['key' => $key, 'saved' => $saved, 'label' => $default['label'], 'start' => $default['start'], 'end' => $default['end']])
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

                            <section class="cfg-section">
                                <div class="cfg-section-head">
                                    <div>
                                        <h5 class="cfg-section-title">Pausas</h5>
                                        <small class="cfg-section-sub">Intervalos bloqueados todos os dias na agenda.</small>
                                    </div>
                                </div>

                                <div class="cfg-slots">
                                    @foreach ($meals as $meal)
                                        <div class="cfg-slot cfg-meal {{ $meal['enabled'] ? '' : 'is-off' }}">
                                            <div class="form-check form-switch cfg-slot-toggle">
                                                <input class="form-check-input meal-toggle" type="checkbox" role="switch" id="{{ $meal['switch'] }}" name="{{ $meal['key'] }}_enabled" value="1" data-target="#{{ $meal['fields'] }}" @checked($meal['enabled'])>
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
                            </section>

                            <section class="cfg-section">
                                <label for="sendLinkMessage" class="form-label mb-0">Mensagem do botão "Enviar link"</label>
                                <small class="cfg-hint mt-0 mb-2">Enviada por WhatsApp ao paciente nas sessões online. A saudação com o nome, o link da sala, a data e o horário são adicionados automaticamente.</small>
                                <textarea id="sendLinkMessage" name="send_link_message" class="form-control" rows="3" maxlength="500" placeholder="{{ \App\Services\AgendaSettingService::DEFAULT_SEND_LINK_TEXT }}">{{ $agendaSettings?->send_link_message }}</textarea>
                                <small class="cfg-hint">Deixe em branco para usar o texto padrão.</small>
                            </section>

                            @php($reminderOptions = (array) config('whatsapp.reminder_options'))
                            <section class="cfg-section">
                                <label class="form-label mb-0">Lembretes automáticos por WhatsApp</label>
                                <small class="cfg-hint mt-0 mb-2">O paciente recebe a confirmação logo ao agendar e um lembrete antes do horário, com os botões "Confirmo presença" e "Preciso remarcar". Nas consultas online, o link da sala chega para você e para o paciente 5 minutos antes. As mensagens não citam terapia nem sessão.</small>
                                <div class="row g-2">
                                    @foreach([['field' => 'reminder_in_person_minutes', 'label' => 'Presencial', 'icon' => 'fas fa-location-dot'], ['field' => 'reminder_online_minutes', 'label' => 'Online', 'icon' => 'fas fa-video']] as $reminder)
                                        @php($current = $agendaSettings ? $agendaSettings->{$reminder['field']} : ($reminder['field'] === 'reminder_online_minutes' ? 120 : 1440))
                                        <div class="col-md-6 col-12">
                                            <label for="{{ $reminder['field'] }}" class="form-label small mb-1"><i class="{{ $reminder['icon'] }} me-1"></i>{{ $reminder['label'] }}</label>
                                            <select id="{{ $reminder['field'] }}" name="{{ $reminder['field'] }}" class="form-select">
                                                <option value="" @selected($current === null)>Não enviar lembrete</option>
                                                @foreach($reminderOptions as $minutes)
                                                    <option value="{{ $minutes }}" @selected($current === $minutes)>{{ __('agenda.reminder_options.'.$minutes) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endforeach
                                </div>
                                <small class="cfg-hint">Lembretes que cairiam entre 22h e 7h são enviados às 21h da noite anterior.</small>
                            </section>

                            @include('settings.partials.actions')
                        </div>
                    </div>

                    <div class="tab-pane fade" id="feriados" role="tabpanel" aria-labelledby="feriados-tab">
                        <div class="dash-card cfg-card">
                            <div class="cfg-head">
                                <div class="cfg-head-icon"><i class="fa-regular fa-calendar-xmark"></i></div>
                                <div class="cfg-head-body">
                                    <h4 class="cfg-head-title">Feriados e exceções</h4>
                                    <p class="cfg-head-sub">Dias em que a agenda fica fechada ou com horário especial.</p>
                                </div>
                                <div class="cfg-head-actions">
                                    <button type="button" class="dash-btn-soft" id="btn-import-holidays">
                                        <i class="fa-solid fa-flag me-1"></i>Importar feriados nacionais
                                    </button>
                                    <button type="button" class="btn btn-primary" id="btn-add-holiday" onclick="addFeriado()">
                                        <i class="fa-solid fa-plus me-1"></i>Adicionar
                                    </button>
                                </div>
                            </div>

                            <div id="container-feriados" class="cfg-items">
                                @foreach ($upcomingHolidays as $holiday)
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

                            <div id="feriados-empty" class="dash-empty {{ $upcomingHolidays->isEmpty() ? '' : 'd-none' }}">
                                <i class="fa-regular fa-calendar-check"></i>
                                Nenhum feriado ou exceção cadastrado.
                                <small class="d-block mt-1">Importe os feriados nacionais ou adicione uma data manualmente.</small>
                            </div>

                            @include('settings.partials.actions')
                        </div>
                    </div>
                </form>

                <div class="tab-pane fade" id="agendamento-online" role="tabpanel" aria-labelledby="agendamento-online-tab">
                    <form id="form-store-booking" class="needs-validation" novalidate>
                        @csrf
                        <div class="dash-card cfg-card">
                            <div class="cfg-head">
                                <div class="cfg-head-icon"><i class="fa-solid fa-link"></i></div>
                                <div class="cfg-head-body">
                                    <h4 class="cfg-head-title">Agendamento online</h4>
                                    <p class="cfg-head-sub">Seus pacientes escolhem serviço, dia e horário sozinhos, conforme os seus dias de atendimento.</p>
                                </div>
                            </div>

                            <div class="cfg-row pt-0">
                                <div class="cfg-row-body">
                                    <label class="cfg-row-title" for="publicBookingEnabled">
                                        Página pública de agendamento
                                        <span id="public-booking-status" class="dash-chip {{ $bookingEnabled ? 'dash-chip-success' : 'dash-chip-muted' }}">{{ $bookingEnabled ? 'Ativa' : 'Inativa' }}</span>
                                    </label>
                                    <span class="cfg-row-detail">Ao ligar ou desligar, a alteração é salva na hora.</span>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="publicBookingEnabled" name="public_booking_enabled" value="1" @checked($bookingEnabled) data-dirty-ignore>
                                </div>
                            </div>

                            <section class="cfg-section">
                                <label for="slug" class="form-label">Endereço da página <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ url('/lp') }}/</span>
                                    <input id="slug" name="slug" type="text" class="form-control" maxlength="60" placeholder="seu-nome" value="{{ $slug }}">
                                    <button id="btn-check-slug" type="button" class="btn btn-outline-secondary" title="Verificar disponibilidade" aria-label="Verificar disponibilidade do endereço">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                <div id="slug-availability" class="small mt-1 d-none" role="status"></div>
                                <small class="cfg-hint">Somente letras minúsculas, números e hífen.</small>
                                <div id="public-booking-link" class="cfg-link-actions">
                                    <a id="public-booking-url" class="dash-btn-soft" href="{{ $slug ? route('public-booking.show', ['slug' => $slug]) : '#' }}" target="_blank" rel="noopener">
                                        <i class="fas fa-external-link-alt me-1"></i>Abrir minha página
                                    </a>
                                    <button id="btn-copy-booking-link" type="button" class="dash-btn-soft">
                                        <i class="fa-regular fa-copy me-1"></i>Copiar link
                                    </button>
                                </div>
                            </section>

                            <section class="cfg-section">
                                <div class="cfg-section-head">
                                    <div>
                                        <h5 class="cfg-section-title">Serviços e valores</h5>
                                        <small class="cfg-section-sub">Ex.: Terapia individual R$ 50,00 · Terapia de casal R$ 90,00. Use o mesmo valor em todos, se preferir.</small>
                                    </div>
                                    <button type="button" class="dash-btn-soft" onclick="addServico()">
                                        <i class="fa-solid fa-plus me-1"></i>Adicionar serviço
                                    </button>
                                </div>

                                <div id="container-servicos" class="cfg-items">
                                    @forelse ($services as $i => $service)
                                        @include('settings.partials.service-item', ['index' => $i, 'service' => $service])
                                    @empty
                                        @include('settings.partials.service-item', ['index' => 0, 'service' => null])
                                    @endforelse
                                </div>
                            </section>

                            @include('settings.partials.actions')
                        </div>
                    </form>
                </div>

                <div class="tab-pane fade" id="google-calendar" role="tabpanel" aria-labelledby="google-calendar-tab">
                    <div class="dash-card cfg-card">
                        <div class="cfg-head">
                            <div class="cfg-head-icon"><i class="fa-brands fa-google"></i></div>
                            <div class="cfg-head-body">
                                <h4 class="cfg-head-title">Google Agenda</h4>
                                <p class="cfg-head-sub">As consultas aparecem na sua agenda do Google, e as sessões online levam o link da sala do PSI4.</p>
                            </div>
                        </div>

                        <div class="cfg-integration">
                            <div class="cfg-row-icon"><i class="fa-regular fa-calendar"></i></div>
                            <div class="cfg-row-body">
                                <span class="cfg-row-title">
                                    Conta Google
                                    @if ($googleStatus)
                                        <span id="googleCalendarStatus" class="dash-chip {{ $chipClass($googleStatus) }}"><i class="{{ $googleStatus->icon() }}"></i>{{ $googleStatus->getText() }}</span>
                                    @else
                                        <span class="dash-chip dash-chip-muted">Não conectada</span>
                                    @endif
                                </span>
                                <span class="cfg-row-detail">
                                    @if ($googleConnected && $googleCalendar->email)
                                        {{ $googleCalendar->email }}
                                    @elseif ($googleNeedsReauth)
                                        Sua conexão com o Google expirou. Reconecte para voltar a sincronizar as consultas.
                                    @else
                                        Conecte para sincronizar as consultas com a sua agenda do Google.
                                    @endif
                                </span>
                            </div>
                            <div class="cfg-integration-actions">
                                @if ($googleConnected)
                                    <button type="button" class="btn btn-outline-danger" onclick="disconnectGoogleCalendar()">Desconectar</button>
                                @else
                                    <a href="{{ route('settings.google-calendar.connect') }}" class="btn btn-primary">
                                        <i class="fab fa-google me-2"></i>{{ $googleNeedsReauth ? 'Reconectar' : 'Conectar com Google' }}
                                    </a>
                                @endif
                            </div>
                        </div>

                        @if ($googleConnected)
                            <form id="form-update-google-calendar" class="mt-3" novalidate>
                                <div class="cfg-row">
                                    <div class="cfg-row-body">
                                        <label class="cfg-row-title" for="googleCalendarId">Calendário das consultas</label>
                                        <span class="cfg-row-detail mb-2">Onde as consultas serão criadas no Google Agenda.</span>
                                        <select id="googleCalendarId" name="calendar_id" class="form-select" style="max-width: 420px;" data-current="{{ $googleCalendar->calendar_id }}">
                                            <option value="{{ $googleCalendar->calendar_id }}" selected>Carregando...</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="cfg-row">
                                    <div class="cfg-row-body">
                                        <label class="cfg-row-title" for="googleInvitePatient">Convidar o paciente pelo Google</label>
                                        <span class="cfg-row-detail">O paciente recebe o convite por e-mail, enviado pelo Google. Ative apenas se ele concordar em ter o e-mail compartilhado com o Google. Por padrão, o evento tem só o primeiro nome do paciente, sem observações.</span>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="googleInvitePatient" name="invite_patient" value="1" @checked($googleCalendar->invite_patient)>
                                    </div>
                                </div>

                                <div class="cfg-row">
                                    <div class="cfg-row-body">
                                        <label class="cfg-row-title" for="googleBlockBusy">Bloquear horários ocupados no Google</label>
                                        <span class="cfg-row-detail">
                                            Horários em que você já tem compromisso no Google Agenda deixam de aparecer como livres na agenda e na página de agendamento online. Só lemos se o horário está ocupado, nunca o título nem os detalhes dos eventos.
                                            @unless ($googleCalendar->canReadFreeBusy())
                                                <br><strong>Para usar, <a href="{{ route('settings.google-calendar.connect') }}">reconecte sua conta</a> e autorize a leitura de disponibilidade.</strong>
                                            @endunless
                                        </span>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="googleBlockBusy" name="block_busy" value="1" @checked($googleCalendar->block_busy) @disabled(! $googleCalendar->canReadFreeBusy())>
                                    </div>
                                </div>

                                @include('settings.partials.actions')
                            </form>
                        @endif
                    </div>
                </div>

                <div class="tab-pane fade" id="psicologo-parent" role="tabpanel" aria-labelledby="psicologo-parent-tab">
                    <form id="form-store-psychologist" class="needs-validation" novalidate>
                        @csrf
                        <div class="dash-card cfg-card">
                            <div class="cfg-head">
                                <div class="cfg-head-icon"><i class="fa-regular fa-id-card"></i></div>
                                <div class="cfg-head-body">
                                    <h4 class="cfg-head-title">Dados do psicólogo</h4>
                                    <p class="cfg-head-sub">Usados na identificação da clínica e nos documentos emitidos. Todos os campos são opcionais.</p>
                                </div>
                            </div>

                            <div class="row g-4">
                                <div class="col-lg-8 order-2 order-lg-1">
                                    <section class="cfg-section">
                                        <h5 class="cfg-section-title mb-3">Identificação</h5>
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label for="nomeClinica" class="form-label">Nome da clínica</label>
                                                <input id="nomeClinica" name="nome_clinica" type="text" class="form-control" placeholder="Nome da clínica" value="{{ $psychologistSettings?->clinic_name }}">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="cnpj" class="form-label">CNPJ</label>
                                                <div class="input-group">
                                                    <input id="cnpj" name="cnpj" type="tel" class="form-control cnpj" placeholder="00.000.000/0000-00" value="{{ maskValue($psychologistSettings?->cnpj, '##.###.###/####-##') }}" inputmode="numeric">
                                                    <button id="btn-search-cnpj" type="button" class="btn btn-outline-secondary" title="Buscar dados pelo CNPJ" aria-label="Buscar dados pelo CNPJ">
                                                        <i class="fas fa-search"></i>
                                                    </button>
                                                </div>
                                                <small class="cfg-hint">Preenche nome e endereço automaticamente.</small>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="crp" class="form-label">CRP</label>
                                                <input id="crp" name="crp" type="tel" class="form-control crp" placeholder="00/000000" inputmode="numeric" value="{{ maskValue($psychologistSettings?->crp, '##/######') }}">
                                            </div>
                                        </div>
                                    </section>

                                    <section class="cfg-section">
                                        <h5 class="cfg-section-title mb-3">Endereço</h5>
                                        @include('settings.partials.address', ['required' => (bool) $agendaSettings?->attendance_mode?->hasInPerson()])
                                    </section>
                                </div>

                                <div class="col-lg-4 order-1 order-lg-2">
                                    <h5 class="cfg-section-title mb-3">Foto</h5>
                                    <div class="photo-cropper cfg-photo">
                                        <div class="photo-canvas-wrap mx-auto" id="photoCanvasWrap">
                                            <canvas id="photoCanvas" width="512" height="512" aria-label="Pré-visualização da foto"></canvas>
                                            <div class="crop-guide" aria-hidden="true"></div>
                                            <div class="photo-change-overlay" aria-hidden="true"><i class="fas fa-camera"></i></div>
                                        </div>
                                        <input id="fotoPsicologo" name="foto_psicologo" type="file" class="d-none" accept="image/jpeg,image/png,image/webp">
                                        <input id="fotoPsicologoCortada" name="foto_psicologo_cortada" type="hidden">
                                        <input id="removerFoto" name="remover_foto" type="hidden" value="0">
                                        <small class="cfg-hint text-center">Clique na imagem para escolher uma foto (JPG, PNG ou WebP).</small>
                                        <div id="photoZoomWrap" class="d-none">
                                            <label for="photoZoom" class="form-label small text-muted mt-3 mb-1">Zoom da foto</label>
                                            <input id="photoZoom" type="range" class="form-range" min="1" max="3" step="0.01" value="1" disabled>
                                            <small class="d-block text-muted">Arraste a foto para ajustar o enquadramento.</small>
                                        </div>
                                        <button id="btn-remove-photo" type="button" class="btn btn-outline-danger btn-sm w-100 mt-3 d-none">
                                            <i class="fas fa-trash me-1"></i>Remover foto
                                        </button>
                                    </div>
                                </div>
                            </div>

                            @include('settings.partials.actions')
                        </div>
                    </form>
                </div>

                <div id="form-store-payment-account" class="tab-pane fade" role="tabpanel" aria-labelledby="pagamentos-parent-tab">
                    <div class="dash-card cfg-card">
                        <div class="cfg-head">
                            <div class="cfg-head-icon"><i class="fa-regular fa-credit-card"></i></div>
                            <div class="cfg-head-body">
                                <h4 class="cfg-head-title">Pagamentos</h4>
                                <p class="cfg-head-sub">Receba dos seus pacientes por Pix e cartão usando a sua conta do Mercado Pago.</p>
                            </div>
                        </div>

                        <div class="cfg-integration">
                            <div class="cfg-row-icon"><i class="fa-solid fa-wallet"></i></div>
                            <div class="cfg-row-body">
                                <span class="cfg-row-title">
                                    Conta Mercado Pago
                                    @if ($paymentStatus)
                                        <span id="paymentAccountStatus" class="dash-chip {{ $chipClass($paymentStatus) }}"><i class="{{ $paymentStatus->icon() }}"></i>{{ $paymentStatus->getText() }}</span>
                                    @else
                                        <span class="dash-chip dash-chip-muted">Não conectada</span>
                                    @endif
                                </span>
                                <span class="cfg-row-detail">
                                    {{ $paymentConnected ? 'Sua conta está pronta para gerar cobranças.' : 'Conecte para começar a gerar cobranças.' }}
                                </span>
                            </div>
                            <div class="cfg-integration-actions">
                                @if ($paymentConnected)
                                    <button type="button" class="btn btn-outline-danger" onclick="disconnectPaymentAccount()">Desconectar</button>
                                @else
                                    <a href="{{ route('settings.payment-account.connect') }}" class="btn btn-primary">
                                        <i class="fas fa-link me-2"></i>Conectar com Mercado Pago
                                    </a>
                                @endif
                            </div>
                        </div>

                        <ul class="cfg-benefits">
                            <li><i class="fa-solid fa-circle-check"></i><span>Cobranças por Pix e cartão para seus pacientes.</span></li>
                            <li><i class="fa-solid fa-circle-check"></i><span>O dinheiro cai direto na sua conta do Mercado Pago.</span></li>
                            <li><i class="fa-solid fa-circle-check"></i><span>Você pode desconectar quando quiser.</span></li>
                        </ul>

                        <div class="cfg-fees">
                            <span class="cfg-row-title">{{ __('payments.fees.title') }}</span>
                            <ul class="cfg-fees-list">
                                @foreach (\App\Helpers\MercadoPagoFeeHelper::rows() as $fee)
                                    <li>
                                        <span>{{ $fee['label'] }} <small class="text-muted">{{ $fee['term'] }}</small></span>
                                        <strong>{{ $fee['percent'] }}</strong>
                                    </li>
                                @endforeach
                            </ul>
                            <small class="cfg-hint">{{ __('payments.fees.patient_interest', ['percent' => \App\Helpers\MercadoPagoFeeHelper::installmentsPatientMax()]) }}</small>
                            <small class="cfg-hint">{{ __('payments.fees.psi4', ['percent' => \App\Helpers\MercadoPagoFeeHelper::formatPercent(\App\Helpers\PlanHelper::paymentFeePercent('free'))]) }}</small>
                            <small class="cfg-hint">{{ __('payments.fees.note', ['date' => \App\Helpers\MercadoPagoFeeHelper::checkedAt()]) }}</small>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="seguranca-parent" role="tabpanel" aria-labelledby="seguranca-parent-tab">
                    <div class="dash-card cfg-card">
                        <div class="cfg-head">
                            <div class="cfg-head-icon"><i class="fa-solid fa-shield-halved"></i></div>
                            <div class="cfg-head-body">
                                <h4 class="cfg-head-title">Segurança</h4>
                                <p class="cfg-head-sub">Proteja sua conta e as ações mais sensíveis do sistema.</p>
                            </div>
                        </div>

                        <div class="cfg-row pt-0">
                            <div class="cfg-row-icon"><i class="fa-solid fa-mobile-screen-button"></i></div>
                            <div class="cfg-row-body">
                                <span class="cfg-row-title">
                                    Autenticação de 2 fatores
                                    <span id="two-factor-status" class="dash-chip {{ $twoFactorEnabled ? 'dash-chip-success' : 'dash-chip-muted' }}">{{ $twoFactorEnabled ? 'Ativa' : 'Inativa' }}</span>
                                </span>
                                <span class="cfg-row-detail">Exige um código do Google Authenticator, além da senha, ao entrar na sua conta.</span>
                            </div>
                            <a href="{{ route('two-factor.index') }}" id="two-factor-manage" class="dash-btn-soft text-nowrap">{{ $twoFactorEnabled ? 'Gerenciar' : 'Ativar' }}</a>
                        </div>

                        <div class="cfg-row">
                            <div class="cfg-row-icon"><i class="fa-solid fa-key"></i></div>
                            <div class="cfg-row-body">
                                <span class="cfg-row-title">
                                    Senha mestre dos prontuários
                                    <span id="encryption-key-status" class="dash-chip {{ $encryptionKeyExists ? 'dash-chip-success' : 'dash-chip-muted' }}">{{ $encryptionKeyExists ? 'Configurada' : 'Não configurada' }}</span>
                                </span>
                                <span class="cfg-row-detail">O conteúdo dos prontuários é criptografado no seu navegador e só abre com a sua senha mestre. Nem a nossa equipe consegue lê-los.</span>
                            </div>
                            <a href="{{ route('encryption-key.index') }}" id="encryption-key-manage" class="dash-btn-soft text-nowrap">{{ $encryptionKeyExists ? 'Gerenciar' : 'Configurar' }}</a>
                        </div>

                        <div class="cfg-row">
                            <div class="cfg-row-icon"><i class="fa-solid fa-shapes"></i></div>
                            <div class="cfg-row-body">
                                <span class="cfg-row-title">Dinâmicas na sessão de vídeo</span>
                                <span class="cfg-row-detail">Ative uma vez para guardar as dinâmicas no prontuário sem precisar entrar no PSI4 durante a sessão.</span>
                            </div>
                            <a href="{{ route('session-activities.index') }}" id="session-activities-link" class="dash-btn-soft text-nowrap">Gerenciar</a>
                        </div>

                        <div class="cfg-row">
                            <div class="cfg-row-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                            <div class="cfg-row-body">
                                <span class="cfg-row-title">Atividade de acesso</span>
                                <span class="cfg-row-detail">Veja quando sua conta foi acessada e quem abriu, editou ou apagou cada prontuário, com data, IP e dispositivo.</span>
                            </div>
                            <a href="{{ route('access-logs.index') }}" id="access-logs-link" class="dash-btn-soft text-nowrap">Ver atividade</a>
                        </div>

                        <div class="cfg-row">
                            <div class="cfg-row-icon"><i class="fa-solid fa-circle-info"></i></div>
                            <div class="cfg-row-body">
                                <span class="cfg-row-title">Segurança e privacidade</span>
                                <span class="cfg-row-detail">O que é criptografado, onde os dados ficam armazenados e quem tem acesso a eles.</span>
                            </div>
                            <a href="{{ route('security') }}" target="_blank" rel="noopener" class="dash-btn-soft text-nowrap">Saiba mais</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modelos usados pelo JS ao adicionar itens --}}
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
