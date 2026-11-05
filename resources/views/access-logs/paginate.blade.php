@if($logs->isNotEmpty())
    @php($total = $logs->total())
    @php($timezone = \App\Helpers\UserHelper::getTimezone())
    <p class="dash-total mb-3">{{ trans_choice('messages.total_records', $total, ['count' => $total]) }}: {{ formatNumber($total) }}</p>

    <div class="dash-card p-0 mb-4" data-access-logs>
        @foreach($logs as $log)
            @include('access-logs._item', ['log' => $log, 'timezone' => $timezone])
        @endforeach
    </div>

    <div class="d-flex justify-content-center">
        {!! $logs->links() !!}
    </div>
@else
    @include('components.empty', [
        'icon' => 'fas fa-clock-rotate-left',
        'message' => ($hasFilters ?? false) ? 'Nenhuma atividade encontrada para este filtro.' : 'Nenhuma atividade registrada.',
    ])
@endif
