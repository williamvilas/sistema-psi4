<?php

declare(strict_types=1);

return [
    'period_too_long' => 'O período do relatório não pode ser maior que 24 meses.',
    'period_invalid' => 'A data final deve ser igual ou maior que a data inicial.',
    'weekdays_short' => ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],
    'weekdays' => ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'],
    'and' => 'e',
    'attendance_insight' => '{1} :days concentra :percent% das faltas do período.|[2,*] :days concentram :percent% das faltas do período.',
    'shifts' => ['morning' => 'de manhã', 'afternoon' => 'à tarde', 'night' => 'à noite'],
    'occupancy_insight' => ':day :shift é o turno com mais horários livres (:percent% ocupado).',
    'no_show_label' => 'Faltas',
    'canceled_label' => 'Cancelamentos',
    'payment_method_not_informed' => 'Não informado',
    'attendance_export_filename' => 'relatorio-faltas-cancelamentos',
    'attendance_export_headings' => [
        'date' => 'Data e hora',
        'patient' => 'Paciente',
        'type' => 'Tipo',
        'status' => 'Situação',
        'price' => 'Valor (R$)',
    ],
];
