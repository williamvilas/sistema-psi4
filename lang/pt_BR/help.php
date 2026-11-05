<?php

declare(strict_types=1);

return [
    'status' => [
        'online' => 'Online agora · resposta em ~5 min',
        'offline' => 'Fora do horário · respondemos :when às :hour',
        'closed' => 'Fora do horário · respondemos assim que possível',
    ],
    'when' => [
        'today' => 'hoje',
        'tomorrow' => 'amanhã',
        'weekdays' => [
            1 => 'na segunda-feira',
            2 => 'na terça-feira',
            3 => 'na quarta-feira',
            4 => 'na quinta-feira',
            5 => 'na sexta-feira',
            6 => 'no sábado',
            7 => 'no domingo',
        ],
    ],
    'days' => [
        1 => 'Segunda',
        2 => 'Terça',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sábado',
        7 => 'Domingo',
    ],
    'days_range' => ':from a :to',
    'hours_range' => ':open às :close',
    'holidays' => 'Feriados nacionais',
    'closed' => 'Fechado',
];
