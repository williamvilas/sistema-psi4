@if(!empty($agenda) && $agenda->isNotEmpty())

    @php($total = $agenda->total())
    <p class="dash-total mb-3">{{ trans_choice('messages.total_records', $total, ['count' => $total]) }}: {{ formatNumber($total) }}</p>

    @php($timezoneByUser = \App\Helpers\UserHelper::getTimezone())
    @php($days = $agenda->getCollection()->groupBy(fn ($item) => $item->scheduled_on->copy()->setTimezone($timezoneByUser)->format('Y-m-d')))

    @foreach($days as $items)
        @php($day = $items->first()->scheduled_on->copy()->setTimezone($timezoneByUser))
        <div class="agenda-day {{ $day->isToday() ? 'is-today' : '' }}">
            @php($weekday = \Illuminate\Support\Str::ucfirst($day->locale('pt_BR')->translatedFormat('l')))
            @php($dayDate = $day->locale('pt_BR')->translatedFormat($day->isCurrentYear() ? 'd \d\e F' : 'd \d\e F \d\e Y'))
            @if($day->isToday() || $day->isTomorrow() || $day->isYesterday())
                <span class="agenda-day-label">{{ $day->isYesterday() ? 'Ontem' : formatDateByAgenda($day) }}</span>
                <span class="agenda-day-date">{{ $weekday }}, {{ $dayDate }}</span>
            @else
                <span class="agenda-day-label">{{ $weekday }}</span>
                <span class="agenda-day-date">{{ $dayDate }}</span>
            @endif
        </div>

        <div class="row">
            @foreach($items as $v)
                @php($id = encrypt($v->id))
                @php($patient = $v->patient)
                @php($scheduledOn = $v->scheduled_on->copy()->setTimezone($timezoneByUser))
                @php($endsAt = $v->ends_at->copy()->setTimezone($timezoneByUser))
                @php($isOpen = $v->isOpen())
                @php($isPending = $v->isPendingAttendance())
                @php($isNow = $v->isInProgress())
                @php($startsIn = $v->startsInMinutes())
                @php($type = \App\Enums\AgendaTypeEnum::tryFrom((string) $v->type) ?? \App\Enums\AgendaTypeEnum::ONLINE)
                @php($isOnline = $type === \App\Enums\AgendaTypeEnum::ONLINE)
                @php($cardState = $isNow ? 'is-now' : ($isPending ? 'is-pending' : ($isOpen ? '' : 'is-closed')))
                <div class="col-12 col-md-6 col-xxl-4 mb-4">
                    <div class="dash-item agenda-card h-100 {{ $cardState }}">

                        <div class="dash-item-head">
                            <div class="agenda-time">
                                <span class="agenda-time-start">{{ $scheduledOn->format('H:i') }}</span>
                                <span class="agenda-time-end">{{ $endsAt->format('H:i') }}</span>
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <a href="{{ route('patients.show', ['id' => encrypt($patient->id)]) }}" class="dash-item-name" title="{{ $patient->name }}">{{ $patient->name }}</a>
                                <div class="dash-item-sub">
                                    <i class="{{ $type->icon() }} me-1"></i> {{ $type->getText() }}
                                </div>
                            </div>
                            <span class="dash-chip dash-chip-{{ $v->status->tone() }} flex-shrink-0 align-self-start">
                                @if($v->status === \App\Enums\AgendaStatusEnum::COMPLETED)<i class="fas fa-check me-1"></i>@endif{{ formatStatusAgenda($v->status) }}
                            </span>
                        </div>

                        <div class="agenda-meta">
                            @if($isNow)
                                <span class="agenda-hint agenda-hint-now">Acontecendo agora</span>
                            @elseif($startsIn !== null)
                                <span class="agenda-hint agenda-hint-soon"><i class="far fa-clock"></i> Começa em {{ $startsIn }} min</span>
                            @elseif($isPending)
                                <span class="agenda-hint agenda-hint-pending"><i class="fas fa-circle-exclamation"></i> Registre a presença</span>
                            @else
                                <span class="agenda-meta-item">{{ formatPhone($patient->phone) }}</span>
                            @endif

                            @if($patient->phone)
                                <a href="{{ route('whatsapp.send', ['phone' => $patient->phone]) }}" target="_blank" rel="noopener" class="dash-round-btn dash-round-btn-success" title="Conversar no WhatsApp">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                            @endif
                        </div>

                        <div class="dash-item-actions agenda-actions flex-wrap">
                            @if($isPending)
                                <button type="button" class="btn agenda-btn-success" onclick="confirmAttendance(@js($id), @js(\App\Enums\AgendaStatusEnum::COMPLETED->value), @js($patient->name))">
                                    <i class="fas fa-check me-1"></i> Compareceu
                                </button>
                                <button type="button" class="btn agenda-btn-danger" onclick="confirmAttendance(@js($id), @js(\App\Enums\AgendaStatusEnum::NO_SHOW->value), @js($patient->name))">
                                    <i class="fas fa-xmark me-1"></i> Faltou
                                </button>
                            @elseif($v->canMarkAttendance())
                                @php($newStatus = $v->status === \App\Enums\AgendaStatusEnum::COMPLETED ? \App\Enums\AgendaStatusEnum::NO_SHOW : \App\Enums\AgendaStatusEnum::COMPLETED)
                                <span class="dash-item-sub">Sessão encerrada</span>
                                <button type="button" class="dash-round-btn dash-round-muted ms-auto btn-change-attendance" title="Alterar situação" aria-label="Alterar situação" onclick="changeAttendance(@js($id), @js($newStatus->value), @js($patient->name))">
                                    <i class="fas fa-pencil"></i>
                                </button>
                            @elseif($isOpen && $isOnline && !empty($v->room_link))
                                <a href="{{ $v->room_link }}" target="_blank" rel="noopener" class="btn btn-primary">
                                    <i class="fas fa-video me-2"></i> Entrar na sala
                                </a>
                                @php($whatsappMessage = app(\App\Services\AgendaSettingService::class)->buildSendLinkMessage($sendLinkText, $patient->name, $scheduledOn, $v->room_link))
                                <a href="{{ route('whatsapp.send', ['phone' => $patient->phone, 'text' => $whatsappMessage]) }}" target="_blank" rel="noopener" class="btn dash-btn-soft">
                                    <i class="far fa-paper-plane me-1"></i> Enviar link
                                </a>
                            @elseif($isOpen)
                                <span class="dash-item-sub">{{ $isOnline ? 'Sem link da sala' : 'Atendimento presencial' }}</span>
                            @else
                                <span class="dash-item-sub">{{ $v->status === \App\Enums\AgendaStatusEnum::CANCELED ? 'Horário liberado na agenda' : 'Sessão encerrada' }}</span>
                            @endif

                            @if($isOpen)
                                <div class="dropdown ms-auto">
                                    <button type="button" class="dash-round-btn dash-round-muted" data-bs-toggle="dropdown" aria-expanded="false" title="Mais ações">
                                        <i class="fas fa-ellipsis-vertical size-icon-ac icon-refresh"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><button type="button" class="dropdown-item text-danger" onclick="cancelAgenda(@js($id), @js($patient->name))"><i class="far fa-calendar-xmark me-2"></i> Cancelar agendamento</button></li>
                                    </ul>
                                </div>
                            @endif
                        </div>

                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="d-flex justify-content-center">
        {!! $agenda->links() !!}
    </div>

@else
    @include('components.empty', [
        'icon' => 'far fa-calendar',
        'message' => (request()->filled('patient_id') || request()->filled('date') || request()->boolean('pending_attendance') || !in_array(request('status'), [null, '', \App\Enums\AgendaStatusEnum::SCHEDULED->value], true)) ? 'Nenhuma sessão encontrada para este filtro.' : 'Nenhuma sessão agendada.',
    ])
@endif
