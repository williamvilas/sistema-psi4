@if(!empty($patients) && $patients->isNotEmpty())

    @php($total = $patients->total())
    <p class="dash-total mb-3">{{ trans_choice('messages.total_records', $total, ['count' => $total]) }}: {{ formatNumber($total) }}</p>

    <div class="row">
        @php($currentDate = \App\Helpers\UserHelper::getDateCurrent())
        @php($timezoneByUser = \App\Helpers\UserHelper::getTimezone())
        @php($whatsAppLinks = app(\App\Services\WhatsAppLinkService::class))
        @foreach($patients as $patient)
            @php($isToday = $patient->birthday_md == $currentDate->format('m-d'))
            @php($id = encrypt($patient->id))
            @php($showUrl = route('patients.show', ['id' => $id]))
            <div class="col-12 col-md-6 col-xl-4 col-xxl-3 mb-4">
                <div class="dash-item h-100 {{ $isToday ? 'is-birthday' : '' }}" data-patient-card>

                    <div class="dash-item-head">
                        @include('components.avatar-initials', ['name' => $patient->name])
                        <div class="min-w-0 flex-grow-1">
                            <a href="{{ $showUrl }}" class="dash-item-name" title="{{ $patient->name }}">{{ $patient->name }}</a>
                            <div class="dash-item-sub text-truncate">
                                @if($patient->birth_date)
                                    {{ calculateAge($patient->birth_date, $timezoneByUser) }} anos · {{ formatDate($patient->birth_date, 'd/m/Y') }}
                                @else
                                    Sem data de nascimento
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="pat-card-info">
                        <i class="fas fa-mobile-alt"></i>
                        <span>{{ formatPhone($patient->phone) }}</span>
                    </div>

                    @if($isToday || $patient->has_duplicate_phone)
                        <div class="pat-card-chips">
                            @if($isToday)
                                <span class="dash-chip dash-chip-success"><i class="fas fa-birthday-cake"></i> Aniversário hoje</span>
                            @endif
                            @if($patient->has_duplicate_phone)
                                <span class="dash-chip dash-chip-warning" title="Existe outro paciente com o mesmo número de WhatsApp"><i class="fas fa-exclamation-triangle"></i> Telefone repetido</span>
                            @endif
                        </div>
                    @endif

                    <div class="dash-item-actions mt-3">
                        <a href="{{ $showUrl }}" class="btn dash-btn-soft"><i class="far fa-eye me-1"></i> Ver ficha</a>

                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('whatsapp.send', array_filter(['phone' => $patient->phone, 'text' => $isToday ? $whatsAppLinks->birthdayMessage($patient->name) : null])) }}" target="_blank" rel="noopener" class="dash-round-btn dash-round-btn-success" title="{{ $isToday ? 'Enviar parabéns pelo WhatsApp' : 'Conversar no WhatsApp' }}">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                            <div class="dropdown">
                                <button type="button" class="dash-round-btn dash-round-muted" data-bs-toggle="dropdown" aria-expanded="false" title="Mais ações">
                                    <i class="fas fa-ellipsis-vertical size-icon-ac icon-refresh"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" title="Editar" href="{{ route('patients.edit', ['id' => $id]) }}"><i class="fas fa-pencil-alt me-2"></i> Editar</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><button type="button" class="dropdown-item text-danger" title="Apagar" onclick="deletePatients(@js($id), @js($patient->name))"><i class="far fa-trash-alt me-2"></i> Apagar</button></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-12 d-flex justify-content-center">
            {!! $patients->links() !!}
        </div>
    </div>

@else
    @include('components.empty', [
        'icon' => 'fas fa-user',
        'message' => ($hasFilters ?? false) ? 'Nenhum paciente encontrado para este filtro.' : 'Você ainda não cadastrou nenhum paciente.',
    ])
@endif
