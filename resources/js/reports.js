function loadFinancialReport(track = true) {
    const form = '#form-report-financial';

    if (!elementExists(form)) {
        return;
    }

    sendRequest(
        '/reports/financial/data',
        'GET',
        form,
        'json',
        function (response) {
            $('#report-summary').html(response.html);
            updateReportPeriodLabel();
            renderReceivedPeriodChart(response.received);
        },
        true,
        null,
        {track: track ? 1 : 0}
    );
}

function renderReceivedPeriodChart(received) {
    if (!elementExists('#received-period-chart')) {
        return;
    }

    const formatCurrency = function (value) {
        return value.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
    };

    Highcharts.chart('received-period-chart', {
        chart: {type: 'column', height: 320, style: {fontFamily: 'inherit'}},
        title: {text: null},
        credits: {enabled: false},
        legend: {enabled: false},
        xAxis: {
            categories: received.data.map(function (item) {
                return item.label;
            }),
            lineColor: '#edf1f5',
            tickLength: 0
        },
        yAxis: {
            min: 0,
            title: {text: null},
            gridLineColor: '#edf1f5',
            labels: {
                formatter: function () {
                    return formatCurrency(this.value);
                }
            }
        },
        tooltip: {
            formatter: function () {
                return '<b>' + this.x + '</b><br>' + formatCurrency(this.y) + '<br>' + this.point.count + ' pagamento(s)';
            }
        },
        plotOptions: {
            column: {
                color: '#1f7a80',
                borderRadius: 6,
                borderWidth: 0,
                maxPointWidth: 48,
                dataLabels: {
                    enabled: received.data.length <= 12,
                    style: {fontWeight: '600', textOutline: 'none'},
                    formatter: function () {
                        return this.y > 0 ? formatCurrency(this.y) : null;
                    }
                }
            }
        },
        series: [{
            name: 'Pagamentos recebidos',
            data: received.data.map(function (item) {
                return {y: item.total, count: item.count};
            })
        }]
    });
}

function reportPeriodRange(period) {
    const today = moment();

    switch (period) {
        case 'this_month':
            return [today.clone().startOf('month'), today];
        case 'full_month':
            return [today.clone().startOf('month'), today.clone().endOf('month')];
        case 'last_month':
            return [today.clone().subtract(1, 'month').startOf('month'), today.clone().subtract(1, 'month').endOf('month')];
        case 'last_30_days':
            return [today.clone().subtract(29, 'days'), today];
        case 'next_30_days':
            return [today, today.clone().add(29, 'days')];
        case 'this_year':
            return [today.clone().startOf('year'), today];
        default:
            return [today, today];
    }
}

function setReportPeriod(period, callback) {
    const [start, end] = reportPeriodRange(period);

    $('#start_date').val(start.format('DD/MM/YYYY'));
    $('#end_date').val(end.format('DD/MM/YYYY'));
    syncReportPeriodChips();

    if (isFunction(callback)) {
        callback();
    }
}

/** Destaca o atalho cujo intervalo coincide com as datas do filtro. */
function syncReportPeriodChips() {
    const start = $('#start_date').val();
    const end = $('#end_date').val();

    $('[data-report-period]').each(function () {
        const [periodStart, periodEnd] = reportPeriodRange($(this).data('report-period'));
        const active = periodStart.format('DD/MM/YYYY') === start && periodEnd.format('DD/MM/YYYY') === end;

        $(this).toggleClass('active', active).attr('aria-pressed', active ? 'true' : 'false');
    });
}

function initReportPeriodChips() {
    if (!elementExists('[data-report-period]')) {
        return;
    }

    syncReportPeriodChips();
    $('#start_date, #end_date').on('input apply.daterangepicker', function () {
        setTimeout(syncReportPeriodChips, 0);
    });
}

/** Mostra o período efetivamente consultado (não o que está sendo digitado). */
function updateReportPeriodLabel() {
    if (!elementExists('#report-period-label')) {
        return;
    }

    const start = moment($('#start_date').val(), 'DD/MM/YYYY');
    const end = moment($('#end_date').val(), 'DD/MM/YYYY');
    const days = end.diff(start, 'days') + 1;

    $('#report-period-label').html('<i class="fas fa-calendar-alt me-1"></i> ' + start.format('DD/MM/YYYY') + ' a ' + end.format('DD/MM/YYYY') + ' · ' + days + (days === 1 ? ' dia' : ' dias'));
}

function loadAttendanceReport(track = true) {
    const form = '#form-report-attendance';

    if (!elementExists(form)) {
        return;
    }

    sendRequest(
        '/reports/attendance/data',
        'GET',
        form,
        'json',
        function (response) {
            $('#report-summary').html(response.html);
            updateReportPeriodLabel();
            renderAttendanceChart(response.chart);
        },
        true,
        null,
        {track: track ? 1 : 0}
    );
}

function renderAttendanceChart(chart) {
    if (!elementExists('#attendance-chart')) {
        return;
    }

    const totals = chart.no_show.map(function (value, index) {
        return value + chart.canceled[index];
    });

    Highcharts.chart('attendance-chart', {
        chart: {type: 'column', height: 300, style: {fontFamily: 'inherit'}},
        title: {text: null},
        credits: {enabled: false},
        legend: {align: 'right', verticalAlign: 'top', itemStyle: {fontWeight: '600'}},
        xAxis: {categories: chart.categories, lineColor: '#edf1f5', tickLength: 0},
        yAxis: {title: {text: null}, min: 0, allowDecimals: false, gridLineColor: '#edf1f5'},
        tooltip: {
            shared: true,
            formatter: function () {
                const total = totals[this.points[0].point.index];
                let html = '<b>' + this.x + '</b>';

                this.points.forEach(function (point) {
                    html += '<br><span style="color:' + point.color + '">\u25CF</span> ' + point.series.name + ': <b>' + point.y + '</b>';
                });

                return html + '<br>Total: <b>' + total + '</b>';
            }
        },
        plotOptions: {
            column: {
                stacking: 'normal',
                borderWidth: 0,
                borderRadius: 6,
                maxPointWidth: 48
            }
        },
        series: [
            {name: 'Faltas', data: chart.no_show, color: '#d64545'},
            {name: 'Cancelamentos', data: chart.canceled, color: '#e0a100'}
        ]
    });
}

function exportAttendanceReport() {
    if (!validForm('#form-report-attendance')) {
        return;
    }

    window.location.href = '/reports/attendance/export?' + $('#form-report-attendance').serialize();
}

function loadOccupancyReport(track = true) {
    const form = '#form-report-occupancy';

    if (!elementExists(form)) {
        return;
    }

    sendRequest(
        '/reports/occupancy/data',
        'GET',
        form,
        'json',
        function (response) {
            $('#report-summary').html(response.html);
            updateReportPeriodLabel();
            renderOccupancyChart(response.chart);
        },
        true,
        null,
        {track: track ? 1 : 0}
    );
}

function renderOccupancyChart(chart) {
    if (!elementExists('#occupancy-chart')) {
        return;
    }

    const formatHours = function (value) {
        return value.toLocaleString('pt-BR', {maximumFractionDigits: 1}) + ' h';
    };

    Highcharts.chart('occupancy-chart', {
        chart: {height: 320, style: {fontFamily: 'inherit'}},
        title: {text: null},
        credits: {enabled: false},
        legend: {align: 'right', verticalAlign: 'top', itemStyle: {fontWeight: '600'}},
        xAxis: {categories: chart.categories, lineColor: '#edf1f5', tickLength: 0},
        yAxis: [
            {
                min: 0,
                title: {text: null},
                gridLineColor: '#edf1f5',
                labels: {
                    formatter: function () {
                        return formatHours(this.value);
                    }
                }
            },
            {
                min: 0,
                max: 100,
                opposite: true,
                title: {text: null},
                gridLineWidth: 0,
                labels: {format: '{value}%'}
            }
        ],
        tooltip: {
            shared: true,
            formatter: function () {
                const index = this.points[0].point.index;
                const rate = chart.rate[index];

                return '<b>' + this.x + '</b>'
                    + '<br>Agendadas: <b>' + formatHours(chart.booked_hours[index]) + '</b>'
                    + '<br>Disponíveis: <b>' + formatHours(chart.capacity_hours[index]) + '</b>'
                    + '<br>Ocupação: <b>' + (rate === null ? '—' : rate.toLocaleString('pt-BR') + '%') + '</b>';
            }
        },
        plotOptions: {
            column: {grouping: false, borderWidth: 0, borderRadius: 6, maxPointWidth: 56}
        },
        series: [
            {type: 'column', name: 'Horas disponíveis', data: chart.capacity_hours, color: '#e3f1f2'},
            {type: 'column', name: 'Horas agendadas', data: chart.booked_hours, color: '#1f7a80', pointPadding: 0.15},
            {
                type: 'spline',
                name: 'Ocupação',
                yAxis: 1,
                data: chart.rate,
                color: '#b27a00',
                lineWidth: 2,
                marker: {radius: 4, symbol: 'circle'},
                connectNulls: true
            }
        ]
    });
}
