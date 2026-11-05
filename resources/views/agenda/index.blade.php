@extends('layouts.app')
@section('title', 'Agenda')

@section('header')
    @include('components.css-dash')
    @include('agenda.partials.index-css')
@endsection

@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            @include('components.icon-agenda', ['css' => 'me-2']) Agenda
        </h3>
    </div>

    @if ($showGoogleCalendarHero)
        @include('agenda.partials.google-calendar-hero')
    @endif

    <div class="row">
        {{-- <div class="col-12 mb-4 col-sm-auto">
            <a href="#" class="btn btn-primary w-100">
                <i class="fa-solid fa-plus me-2"></i> Novo agendamento
            </a>
        </div>--}}

        {{-- Link da página pública: copiar é a ação principal; sem página ativa, convida a ativar --}}
        <div @class(['col-12 mb-4 col-lg-auto agenda-listing', 'd-none' => $showGoogleCalendarHero])>
            @if ($publicBooking['enabled'])
                <div class="agenda-share" role="group" aria-label="Link da página de agendamento" data-url="{{ $publicBooking['url'] }}">
                    <span class="agenda-share-url" title="{{ $publicBooking['url'] }}">
                        <i class="fa-solid fa-link"></i>
                        <span class="text-truncate">{{ preg_replace('#^https?://#', '', $publicBooking['url']) }}</span>
                    </span>
                    <div class="agenda-share-actions">
                        <button type="button" class="btn btn-primary agenda-share-copy" onclick="copyBookingLink(this)">
                            <i class="fa-regular fa-copy me-2"></i><span>Copiar link</span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary agenda-share-native d-none" onclick="shareBookingLink(this)">
                            <i class="fa-solid fa-share-nodes me-2"></i>Compartilhar
                        </button>
                        <a href="{{ $publicBooking['url'] }}" target="_blank" rel="noopener" class="btn btn-outline-secondary agenda-share-open" title="Abrir página de agendamento" aria-label="Abrir página de agendamento">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                </div>
                @unless ($publicBooking['custom_slug'])
                    <a href="{{ route('settings.index') }}#agendamento-online" class="agenda-share-hint">
                        <i class="fa-regular fa-pen-to-square me-1"></i>Personalize o endereço do seu link
                    </a>
                @endunless
            @else
                <div class="agenda-share is-off">
                    <span class="agenda-share-url">
                        <i class="fa-solid fa-link-slash"></i>
                        <span>Agendamento online desativado</span>
                    </span>
                    <div class="agenda-share-actions">
                        <a href="{{ route('settings.index') }}#agendamento-online" class="btn btn-outline-secondary">Ativar em Configurações</a>
                    </div>
                </div>
            @endif
        </div>

        {{-- Quem nunca usou a agenda vê só o convite do Google; a listagem aparece ao dispensar --}}
        <div @class(['col-6 mb-4 col-sm-auto agenda-listing', 'd-none' => $showGoogleCalendarHero])>
            @include('components.filter', ['css' => 'btn-outline-secondary'])
        </div>

        <div @class(['col-6 mb-4 col-sm-auto agenda-listing', 'd-none' => $showGoogleCalendarHero])>
            <button type="button" class="btn btn-outline-secondary w-100" onclick="paginateAgenda()" title="Atualizar">
                <i class="fas fa-sync icon-refresh"></i><span class="d-sm-none ms-2">Atualizar</span>
            </button>
        </div>
    </div>

    <div @class(['dash-filter-chips mb-3 agenda-listing', 'd-none' => $showGoogleCalendarHero]) role="group" aria-label="Filtrar por status">
        @foreach([
            \App\Enums\AgendaStatusEnum::SCHEDULED->value => 'Agendados',
            \App\Enums\AgendaStatusEnum::CONFIRMED->value => 'Confirmados',
            \App\Enums\AgendaStatusEnum::COMPLETED->value => 'Finalizados',
            \App\Enums\AgendaStatusEnum::NO_SHOW->value => 'Faltas',
            \App\Enums\AgendaStatusEnum::CANCELED->value => 'Cancelados',
            '' => 'Todos',
        ] as $value => $label)
            <button type="button" class="dash-filter-chip" data-status="{{ $value }}" onclick="filterAgendaByStatus(this.dataset.status)">{{ $label }}</button>
            @if ($value === \App\Enums\AgendaStatusEnum::CONFIRMED->value)
                {{-- Não é um status: sessões já encerradas que ainda aguardam o registro de presença --}}
                <button type="button" class="dash-filter-chip" data-pending-attendance="1" onclick="filterAgendaByPendingAttendance()">Sem presença</button>
            @endif
        @endforeach
    </div>

    <div @class(['list-paginate agenda-listing', 'd-none' => $showGoogleCalendarHero])>
        @unless ($showGoogleCalendarHero)
            <div class="dash-skeleton"></div>
            <div class="dash-skeleton"></div>
        @endunless
    </div>

    <!-- Modal Filter -->
    <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="filterModalLabel">Filtrar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="form-search" autocomplete="off">
                        @php($pendingAttendance = request()->boolean('pending_attendance'))
                        <input type="hidden" name="pending_attendance" value="{{ $pendingAttendance ? 1 : '' }}">
                        <div class="row">
                            <div class="col-lg-12 col-sm-12">
                                <div class="form-group">
                                    <label for="patient_id">Paciente: </label>
                                    <select class="form-select" name="patient_id" id="patient_id"></select>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-12">
                                <div class="form-group">
                                    <label>Status: </label>
                                    <select name="status" class="form-select">
                                        <option value="">Todas</option>
                                        @php($selectedStatus = $pendingAttendance ? '' : request('status', \App\Enums\AgendaStatusEnum::SCHEDULED->value))
                                        @foreach(\App\Enums\AgendaStatusEnum::values() as $status)
                                            <option value="{{ $status }}" @selected($status === $selectedStatus)>{{ __('agenda_status.'.$status) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-12">
                                <div class="form-group">
                                    <label for="type">Modalidade: </label>
                                    <select name="type" id="type" class="form-select">
                                        <option value="">Todas</option>
                                        @foreach(\App\Enums\AgendaTypeEnum::cases() as $type)
                                            <option value="{{ $type->value }}" @selected($type->value === request('type'))>{{ $type->getText() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-12 col-sm-12">
                                <div class="form-group">
                                    <label for="date">Período: </label>
                                    <input type="tel" name="date" id="date" class="form-control date_range_future" value="{{ request('date') }}">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="clearFiltersAgenda()">Limpar</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="paginateAgenda()">Buscar</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('footer')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initDateRangeFuturePickers();
            initPatientFilter();
            initBookingLinkShare();
            @unless ($showGoogleCalendarHero)
                paginateAgenda();
            @endunless
        });
        // Status escolhido no modal substitui o filtro "Sem presença" (são exclusivos)
        document.querySelector('#form-search [name="status"]').addEventListener('change', function () {
            document.querySelector('#form-search [name="pending_attendance"]').value = '';
        });
        document.getElementById('form-search').addEventListener('submit', function (e) {
            e.preventDefault();
            paginateAgenda();
        });
    </script>
@endsection

