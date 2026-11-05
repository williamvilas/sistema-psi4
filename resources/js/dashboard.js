function reportDashboard(callback = null) {
    sendRequest(
        '/dashboard/report',
        'GET',
        null,
        'json',
        function(response) {
            $('.list-paginate').html(response.html);
            renderDashboardWeeklyChart(response.chart);
            if (isFunction(callback)) {
                callback();
            }
        }
    );
}

function renderDashboardWeeklyChart(chart) {
    if (!elementExists('#dashboard-weekly-sessions')) {
        return;
    }

    Highcharts.chart('dashboard-weekly-sessions', {
        chart: {type: 'column', backgroundColor: 'transparent', style: {fontFamily: 'inherit'}},
        title: {text: null},
        credits: {enabled: false},
        legend: {itemStyle: {color: '#6b7a90', fontWeight: '500', fontSize: '14px'}},
        xAxis: {
            categories: chart.categories,
            lineColor: '#edf1f5',
            tickLength: 0,
            labels: {style: {color: '#6b7a90', fontSize: '13px'}}
        },
        yAxis: {
            title: {text: null},
            min: 0,
            allowDecimals: false,
            gridLineDashStyle: 'Dash',
            gridLineColor: '#edf1f5',
            labels: {style: {color: '#6b7a90', fontSize: '13px'}}
        },
        tooltip: {style: {fontSize: '14px'}, shared: true, borderRadius: 12, borderWidth: 0, shadow: true},
        plotOptions: {column: {stacking: 'normal', borderWidth: 0, borderRadius: 6, pointPadding: 0.1, groupPadding: 0.1}},
        series: [
            {name: 'Finalizadas', data: chart.completed, color: '#1f7a80'},
            {name: 'Faltas', data: chart.no_show, color: '#f08a7e'},
            {name: 'Cancelamentos', data: chart.canceled, color: '#f2c14e'}
        ]
    });
}
