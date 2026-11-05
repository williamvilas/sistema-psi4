<?php

declare(strict_types=1);

return [
    'not_available' => 'O Horário não está mais disponível. Escolha outro horário.',
    'not_found' => 'Agendamento não encontrado ou já cancelado.',
    'attendance_not_found' => 'Agendamento não encontrado ou ainda não finalizado.',
    'attendance_completed' => 'Sessão marcada como finalizada.',
    'attendance_no_show' => 'Sessão marcada como falta.',
    'canceled' => 'Agendamento cancelado. O horário está disponível novamente.',
    'patient_not_found' => 'Paciente não encontrado.',
    'online' => 'Online',
    'in_person' => 'Presencial',
    'attendance_mode' => [
        'online' => 'Só online',
        'in_person' => 'Só presencial',
        'both' => 'Online e presencial',
    ],
    'day_mode' => [
        'both' => 'Os dois',
        'online' => 'Online',
        'in_person' => 'Presencial',
    ],
    'mode_not_allowed' => 'Essa modalidade não está disponível neste horário. Escolha outra modalidade ou outro horário.',
    'periods_overlap' => 'Os períodos do mesmo dia não podem se sobrepor. Para atender online e presencial no mesmo horário, escolha "Os dois".',
    'address_required' => 'Para atender presencialmente, preencha o endereço do consultório (rua, número e cidade) em Configurações › Psicólogo.',
    'address_required_onboarding' => 'Para atender presencialmente, informe o endereço do consultório (rua, número e cidade).',
    'end_before_start' => 'O horário de término precisa ser depois do horário de início.',
    'google_hero_title' => 'Conecte sua agenda ao Google Agenda',
    'google_hero_text' => ' Mantenha suas consultas organizadas na sua agenda do Google, com o link da sala de cada atendimento online. Menos tarefas manuais, mais praticidade e mais tempo para cuidar dos seus pacientes.',
    'google_hero_note' => 'Só a data, o horário e o primeiro nome do paciente vão para o Google. O prontuário continua só com você.',
    'google_hero_connect' => 'Conectar com Google Agenda',
    'google_hero_dismiss' => 'Agora não',
    'google_hero_dismissed' => 'Tudo bem! Quando quiser, você pode conectar o Google Agenda em Configurações.',

    // Antecedência do lembrete por WhatsApp (minutos => texto), ver config('whatsapp.reminder_options')
    'reminder_options' => [
        60 => '1 hora antes',
        120 => '2 horas antes',
        180 => '3 horas antes',
        360 => '6 horas antes',
        720 => '12 horas antes',
        1440 => '1 dia antes',
        2880 => '2 dias antes',
    ],
];
