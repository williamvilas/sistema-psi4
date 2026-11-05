<?php

declare(strict_types=1);

// Os textos enviados por WhatsApp (ver config/whatsapp.php) precisam ser idênticos aos modelos aprovados na Meta.
// Não citam terapia nem sessão: a notificação pode ser vista por terceiros no celular do paciente.
return [
    'patient' => [
        'agenda_scheduled' => 'Olá, :patient! Seu horário com :psychologist está marcado para :date. Até lá!',
        'agenda_canceled' => 'Olá, :patient! Seu horário com :psychologist em :date foi cancelado.',
        'agenda_no_show' => 'Olá, :patient! Sentimos sua falta na sessão com :psychologist em :date.',
        'agenda_completed' => 'Olá, :patient! Obrigado por participar da sessão com :psychologist em :date.',
        'agenda_reminder' => 'Olá, :patient! Passando para lembrar do seu horário com :psychologist em :date. Podemos contar com você?',
        'agenda_room_link' => "Olá, :patient! Seu horário com :psychologist começa em 5 minutos. Acesse por este link: :link\n\nAté já!",
    ],
    'psychologist' => [
        'agenda_scheduled' => 'Novo agendamento pela sua página: :patient em :date. Os detalhes estão na sua agenda do PSI4.',
        'agenda_meet_missing' => 'Não foi possível gerar o link do Google Meet da sessão com :patient em :date. Adicione o link manualmente.',
        'agenda_attendance' => 'A sessão com :patient em :date já passou. Informe na agenda se o paciente compareceu ou faltou.',
        'agenda_room_link' => "Seu atendimento com :patient começa em 5 minutos. Link da sala: :link\n\nBom atendimento!",
        'agenda_reschedule' => 'Pedido para remarcar: :patient não poderá comparecer em :date. Entre em contato para combinar uma nova data.',
    ],

    // Respostas livres às mensagens do paciente (dentro da janela de 24h, sem modelo)
    'reply' => [
        'confirmed' => 'Presença confirmada. Até lá!',
        'reschedule' => 'Certo! Avisamos :psychologist para combinar um novo horário com você.',
        'inactive' => 'Este horário não está mais ativo. Para combinar um novo, fale diretamente com :psychologist.',
        'opt_out' => 'Pronto, você não vai mais receber avisos de horário por aqui.',
        'default' => 'Oi! Este número só envia avisos de horário e não acompanha as mensagens. Para falar com quem te atende, use o contato de costume. Se não quiser mais receber avisos, responda SAIR.',
    ],

    // Motivos de não envio gravados no campo `error` da notificação
    'whatsapp_not_configured' => 'Envio por WhatsApp não configurado.',
    'whatsapp_rejected' => 'O WhatsApp não aceitou a mensagem. Confira se o número tem WhatsApp.',
    'whatsapp_plan_unavailable' => 'Os avisos automáticos por WhatsApp estão disponíveis no plano Profissional.',
    'whatsapp_limit_reached' => 'Limite mensal de avisos automáticos por WhatsApp atingido.',
    'whatsapp_opted_out' => 'O paciente não recebe avisos automáticos por WhatsApp.',
    'whatsapp_room_link_missing' => 'A consulta não tem link da sala.',
];
