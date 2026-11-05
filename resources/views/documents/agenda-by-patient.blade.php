<div class="agenda-item mb-2">
    @if (! empty($items) && $items->isNotEmpty())
        @foreach ($items as $v)
            @php
                $scheduledOn = \Carbon\Carbon::parse($v->scheduled_on)->setTimezone($timezone);
                $endsAt = \Carbon\Carbon::parse($v->ends_at)->setTimezone($timezone);
                $statusAgendaText = __('agenda_status.' . $v->status->value)
            @endphp

            <label class="d-flex align-items-center justify-content-between gap-2 p-2 border rounded bg-light">
                <span class="d-flex align-items-center gap-2">
                    <input type="checkbox" class="agenda-check" data-label="{{ $scheduledOn->format('d/m/Y H:i') }}" value="{{ $v->scheduled_on }}|{{ $scheduledOn->format('H:i') }}|{{ $endsAt->format('H:i') }}">
                    <span>
                        <strong>{{ $scheduledOn->format('d/m/Y') }}</strong><br>
                        <small>{{ $scheduledOn->format('H:i') }} às {{ $endsAt->format('H:i') }}</small>
                    </span>
                </span>
                <span class="badge bg-primary-subtle text-primary">{{ $statusAgendaText }}</span>
            </label>
        @endforeach

        @php
            $currentPage = $items->currentPage();
            $lastPage = $items->lastPage();
            $pageWindow = 2;
            $startPage = max(1, $currentPage - $pageWindow);
            $endPage = min($lastPage, $currentPage + $pageWindow);

            if ($currentPage <= 3) {
                $startPage = 1;
                $endPage = min($lastPage, 5);
            }

            if ($currentPage >= $lastPage - 2) {
                $endPage = $lastPage;
                $startPage = max(1, $lastPage - 4);
            }

            $visiblePages = range($startPage, $endPage);
        @endphp

        @if ($lastPage > 1)
            <nav class="mt-3" aria-label="Paginação de agendas">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    @if ($startPage > 1)
                        <li class="page-item">
                            <a href="#" class="page-link js-page-link" data-page="1" data-patient-id="{{ $patientId }}" data-callback="getAgendaPatientByDocuments" onclick="return false;">1</a>
                        </li>
                        @if ($startPage > 2)
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        @endif
                    @endif

                    @foreach ($visiblePages as $page)
                        <li class="page-item {{ $page == $currentPage ? 'active' : '' }}">
                            <a
                                href="#"
                                class="page-link js-page-link"
                                data-page="{{ $page }}"
                                data-patient-id="{{ $patientId }}"
                                data-callback="getAgendaPatientByDocuments"
                                onclick="return false;"
                            >
                                {{ $page }}
                            </a>
                        </li>
                    @endforeach

                    @if ($endPage < $lastPage)
                        @if ($endPage < $lastPage - 1)
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        @endif
                        <li class="page-item">
                            <a href="#" class="page-link js-page-link" data-page="{{ $lastPage }}" data-patient-id="{{ $patientId }}" data-callback="getAgendaPatientByDocuments" onclick="return false;">{{ $lastPage }}</a>
                        </li>
                    @endif
                </ul>
            </nav>
        @endif
    @else
        <div class="text-muted">Nenhuma sessão finalizada.</div>
    @endif
</div>
