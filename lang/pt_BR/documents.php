<?php

declare(strict_types=1);

return [
    'psychological_certificate' => 'Atestado Psicológico',
    'psychologist_statement' => 'Declaração do Psicólogo',
    'attendance_statement' => 'Declaração de Comparecimento',
    'psychological_report' => 'Relatório Psicológico',

    'descriptions' => [
        'psychological_certificate' => 'Atesta as condições psicológicas do paciente, com finalidade e conclusão da avaliação.',
        'psychologist_statement' => 'Informa que o paciente está em acompanhamento, desde quando e com qual frequência.',
        'attendance_statement' => 'Comprova a presença do paciente nas sessões realizadas que você selecionar.',
        'psychological_report' => 'Apresenta demanda, procedimento, análise e conclusão do acompanhamento psicológico.',
    ],

    'not_found' => 'Documento não encontrado.',
    'field_not_allowed' => 'Este campo não pode ser salvo como padrão.',

    'frequency' => [
        'daily' => 'Diário',
        'weekly' => 'Semanal',
        'monthly' => 'Mensal',
        'quarterly' => 'Trimestral',
        'semiannual' => 'Semestral',
        'annual' => 'Anual',
    ],

    'frequency_adjective' => [
        'daily' => 'diários',
        'weekly' => 'semanais',
        'monthly' => 'mensais',
        'quarterly' => 'trimestrais',
        'semiannual' => 'semestrais',
        'annual' => 'anuais',
    ],

    'fields' => [
        'psychological_certificate' => [
            'text_1' => 'Finalidade',
            'text_2' => 'Descrição e conclusão da avaliação',
        ],
        'psychologist_statement' => [
            'date' => 'Acompanhamento desde',
            'frequency' => 'Frequência dos atendimentos',
            'text_1' => 'Finalidade da declaração',
        ],
        'psychological_report' => [
            'text_1' => '1. Descrição da demanda',
            'text_2' => '2. Procedimento',
            'text_3' => '3. Análise',
            'text_4' => '4. Conclusão',
            'text_5' => 'Finalidade',
            'text_6' => 'Solicitante',
        ],
    ],

    'defaults' => [
        'psychologist_statement' => [
            'text_1' => 'Comprovação da realização de acompanhamento psicológico.',
        ],
        'psychological_report' => [
            'text_1' => 'Este relatório psicológico foi elaborado a pedido do(a) solicitante, com o objetivo de apresentar informações sobre o acompanhamento psicológico realizado, respeitando os limites éticos da profissão.',
        ],
    ],

    'default_saved' => 'Texto padrão salvo com sucesso.',
    'default_restored' => 'Texto padrão restaurado.',
];
