{{-- $form: id do formulário | $load: função JS que carrega o relatório | $export: função JS de exportação (opcional) | $future: atalhos com datas futuras --}}
@php
    $future = $future ?? false;
    $periods = array_filter([
        ($future ? 'full_month' : 'this_month') => 'Este mês',
        'last_month' => 'Mês passado',
        'last_30_days' => 'Últimos 30 dias',
        'next_30_days' => $future ? 'Próximos 30 dias' : null,
        'this_year' => $future ? null : 'Este ano',
    ]);
@endphp
<div class="dash-card report-filter mb-4">
    <form id="{{ $form }}" autocomplete="off">
        <div class="row g-3 align-items-end">
            <div class="col-6 col-md-4 col-lg-2">
                <label for="start_date" class="dash-eyebrow">De</label>
                <input type="tel" class="form-control date date_only" id="start_date" name="start_date" value="{{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}" required>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label for="end_date" class="dash-eyebrow">Até</label>
                <input type="tel" class="form-control date date_only" id="end_date" name="end_date" value="{{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}" required>
            </div>
            <div class="col-12 col-md-auto">
                <button type="button" class="btn btn-primary w-100" onclick="{{ $load }}()">
                    <i class="fas fa-search me-2"></i> Buscar
                </button>
            </div>
            @if(!empty($export))
                <div class="col-12 col-md-auto">
                    <button type="button" class="btn btn-outline-secondary w-100" onclick="{{ $export }}()">
                        <i class="fas fa-file-excel me-2"></i> Exportar Excel
                    </button>
                </div>
            @endif
        </div>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
            <div class="dash-filter-chips" role="group" aria-label="Atalhos de período">
                @foreach($periods as $period => $label)
                    <button type="button" class="dash-filter-chip" data-report-period="{{ $period }}" onclick="setReportPeriod('{{ $period }}', {{ $load }})">{{ $label }}</button>
                @endforeach
            </div>
            <small id="report-period-label" class="dash-total"></small>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        initOnlyDatePickers();
        initReportPeriodChips();
    });
</script>
