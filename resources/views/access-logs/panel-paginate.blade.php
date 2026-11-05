{{-- Lista paginada do painel lateral de acessos do prontuário (paginação própria: .js-page-link). --}}
@if($logs->isNotEmpty())
    @php($total = $logs->total())
    @php($timezone = \App\Helpers\UserHelper::getTimezone())
    <p class="doc-access-total">{{ trans_choice('messages.total_records', $total, ['count' => $total]) }}: {{ formatNumber($total) }}</p>

    <div data-access-logs-panel>
        @foreach($logs as $log)
            @include('access-logs._item', ['log' => $log, 'timezone' => $timezone, 'compact' => true])
        @endforeach
    </div>

    @if($logs->hasPages())
        <nav class="doc-access-pagination" aria-label="Paginação dos acessos">
            @if($logs->onFirstPage())
                <span class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true"><i class="fas fa-chevron-left"></i></span>
            @else
                <a href="{{ $logs->previousPageUrl() }}" class="btn btn-outline-secondary btn-sm js-page-link" rel="prev" title="Página anterior" aria-label="Página anterior"><i class="fas fa-chevron-left"></i></a>
            @endif

            <span class="doc-access-page">Página {{ $logs->currentPage() }} de {{ $logs->lastPage() }}</span>

            @if($logs->hasMorePages())
                <a href="{{ $logs->nextPageUrl() }}" class="btn btn-outline-secondary btn-sm js-page-link" rel="next" title="Próxima página" aria-label="Próxima página"><i class="fas fa-chevron-right"></i></a>
            @else
                <span class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true"><i class="fas fa-chevron-right"></i></span>
            @endif
        </nav>
    @endif
@else
    <div class="doc-access-empty">
        <i class="fas fa-clock-rotate-left fa-2x mb-3"></i>
        <p class="mb-1 fw-semibold">Nenhuma atividade encontrada.</p>
        @if($hasFilters ?? false)
            <small class="d-block">Revise os filtros aplicados.</small>
        @endif
    </div>
@endif
