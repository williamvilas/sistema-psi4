var specialDatesPicker = [
    '01/01',
    '21/04',
    '01/05',
    '07/09',
    '12/10',
    '02/11',
    '15/11',
    '20/11',
    '25/12',
];

var specificHolidaysPicker = [

];

function initDatePickers() {
    moment.locale('pt-br');
    $('.date_current:not([data-initialized])').each(function () {
        $(this).attr('autocomplete', 'off');
        const defaultDate = moment().subtract(40, 'seconds');

        $(this).daterangepicker({
            singleDatePicker: true,
            showDropdowns: true,
            timePicker: true,
            timePicker24Hour: true,
            timePickerSeconds: true,
            autoApply: true,
            startDate: defaultDate,
            locale: {
                format: "DD/MM/YYYY HH:mm:ss",
                separator: " - ",
                applyLabel: "Aplicar",
                cancelLabel: "Cancelar",
                fromLabel: "De",
                toLabel: "Para",
                customRangeLabel: "Personalizado",
                weekLabel: "W",
                daysOfWeek: ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sab"],
                monthNames: ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"],
                firstDay: 0
            },
            alwaysShowCalendars: true,
            minYear: parseInt(moment().subtract(5, 'years').format('YYYY')),
            maxYear: parseInt(moment().format('YYYY'), 10),
            maxDate: moment(),
            autoUpdateInput: false
        }).on('apply.daterangepicker', function (ev, picker) {
            $(this).val(picker.startDate.format('DD/MM/YYYY HH:mm:ss'));
        });

        $(this).val(defaultDate.format('DD/MM/YYYY HH:mm:ss'));
        $(this).attr('data-initialized', 'true');
    });
}

function initOnlyDatePickers(callback = null, minDate = null) {

    let options = {
        singleDatePicker: true,
        showDropdowns: true,
        timePicker: false,
        timePicker24Hour: false,
        timePickerSeconds: false,
        autoApply: true,
        locale: {
            format: "DD/MM/YYYY",
            separator: " - ",
            applyLabel: "Aplicar",
            cancelLabel: "Cancelar",
            fromLabel: "De",
            toLabel: "Para",
            customRangeLabel: "Personalizado",
            weekLabel: "W",
            daysOfWeek: ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sab"],
            monthNames: ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"],
            firstDay: 0
        },
        alwaysShowCalendars: true,
        minYear: parseInt(moment().subtract(2, 'years').format('YYYY')),
        maxYear: parseInt(moment().add(3, 'years').format('YYYY')),
        autoUpdateInput: false,
        isCustomDate: function(date) {

            var dataAtual = date.format('DD/MM');
            var dataAtualCompleto = date.format('DD/MM/YYYY');
            if (specialDatesPicker.indexOf(dataAtual) !== -1 || specificHolidaysPicker.indexOf(dataAtualCompleto) !== -1) {
                return 'date-special';
            }

            return '';
        }
    };

    if (minDate) {
        options.minDate = minDate;
    }

    $('.date_only:not([data-initialized])').each(function () {
        $(this).daterangepicker(options).on('apply.daterangepicker', function (ev, picker) {
            $(this).val(picker.startDate.format('DD/MM/YYYY'));

            if (isFunction(callback)) {
                setTimeout(function () {
                    callback();
                }, 50);
            }
        });

        $(this).attr('data-initialized', 'true');
    });

    setTimeout(function() {
        $('.date_only')
            .attr('autocomplete', 'off')
            .attr('autocorrect', 'off')
            .attr('spellcheck', 'false');
    }, 200);
}

function initDateRangePickers(callback = null, allowPastDates = true) {
    moment.locale('pt-br');
    $('.date_range_current:not([data-initialized])').each(function () {
        $(this).attr('autocomplete', 'off');
        var options = {
            singleDatePicker: false,
            showDropdowns: true,
            timePicker: false,
            timePicker24Hour: false,
            timePickerSeconds: false,
            autoApply: true,
            ranges: {
                'Hoje': [moment(), moment()],
                'Ontem': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Últimos 7 Dias': [moment().subtract(6, 'days'), moment()],
                'Últimos 30 Dias': [moment().subtract(29, 'days'), moment()],
                'Este Mês': [moment().startOf('month'), moment().endOf('month')],
                'Último Mês': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            },
            locale: {
                format: "DD/MM/YYYY HH:mm:ss",
                separator: " - ",
                applyLabel: "Aplicar",
                cancelLabel: "Cancelar",
                fromLabel: "De",
                toLabel: "Para",
                customRangeLabel: "Personalizado",
                weekLabel: "W",
                daysOfWeek: ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sab"],
                monthNames: ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"],
                firstDay: 0
            },
            alwaysShowCalendars: true,
            minYear: parseInt(moment().subtract(5, 'years').format('YYYY')),
            maxYear: parseInt(moment().format('YYYY'), 10),
            autoUpdateInput: false,
            isCustomDate: function(date) {

                var dataAtual = date.format('DD/MM');
                var dataAtualCompleto = date.format('DD/MM/YYYY');
                if (specialDatesPicker.indexOf(dataAtual) !== -1 || specificHolidaysPicker.indexOf(dataAtualCompleto) !== -1) {
                    return 'date-special';
                }

                return '';
            }
        };

        if (!allowPastDates) {
            options.minDate = moment();
        }

        $(this).daterangepicker(options).on('apply.daterangepicker', function (ev, picker) {
            $(this).val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));

            if (isFunction(callback)) {
                callback();
            }
        });

        $(this).attr('data-initialized', 'true');
    });
}

function initDateRangeFuturePickers(callback = null) {
    moment.locale('pt-br');
    $('.date_range_future:not([data-initialized])').each(function () {
        $(this).attr('autocomplete', 'off');

        $(this).daterangepicker({
            singleDatePicker: false,
            showDropdowns: true,
            timePicker: false,
            autoApply: true,
            ranges: {
                'Hoje': [moment(), moment()],
                'Amanhã': [moment().add(1, 'days'), moment().add(1, 'days')],
                'Próximos 7 Dias': [moment(), moment().add(6, 'days')],
                'Próximos 30 Dias': [moment(), moment().add(29, 'days')],
                'Este Mês': [moment().startOf('month'), moment().endOf('month')],
                'Próximo Mês': [moment().add(1, 'month').startOf('month'), moment().add(1, 'month').endOf('month')],
                'Últimos 7 Dias': [moment().subtract(6, 'days'), moment()],
                'Último Mês': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            },
            locale: {
                format: "DD/MM/YYYY",
                separator: " - ",
                applyLabel: "Aplicar",
                cancelLabel: "Cancelar",
                fromLabel: "De",
                toLabel: "Para",
                customRangeLabel: "Personalizado",
                weekLabel: "W",
                daysOfWeek: ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sab"],
                monthNames: ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"],
                firstDay: 0
            },
            alwaysShowCalendars: true,
            minYear: parseInt(moment().subtract(5, 'years').format('YYYY')),
            maxYear: parseInt(moment().add(5, 'years').format('YYYY'), 10),
            autoUpdateInput: false,
            isCustomDate: function (date) {
                const dataAtual = date.format('DD/MM');
                const dataAtualCompleto = date.format('DD/MM/YYYY');

                return specialDatesPicker.indexOf(dataAtual) !== -1 || specificHolidaysPicker.indexOf(dataAtualCompleto) !== -1
                    ? 'date-special'
                    : '';
            }
        }).on('apply.daterangepicker', function (ev, picker) {
            $(this).val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));

            if (isFunction(callback)) {
                callback();
            }
        });

        $(this).attr('data-initialized', 'true');
    });
}
