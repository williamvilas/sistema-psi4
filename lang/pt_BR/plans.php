<?php

declare(strict_types=1);

return [
    'free' => 'Grátis',
    'professional' => 'Profissional',

    'total_agenda' => '{1} Até :total agendamento por mês|[2,*] Até :total agendamentos por mês',
    'total_patients' => '{1} Até :total paciente ativo por mês|[2,*] Até :total pacientes ativos por mês',
    'total_medical_records' => '{1} Até :total prontuário por mês|[2,*] Até :total prontuários por mês',
    'total_scales' => '{1} Até :total aplicação de escala por mês|[2,*] Até :total aplicações de escalas por mês',
    'total_payment_orders' => '{1} Até :total cobrança por mês|[2,*] Até :total cobranças por mês',
    'total_documents_issued' => '{1} Até :total documento emitido por mês|[2,*] Até :total documentos emitidos por mês',
    'total_whatsapp_manual' => '{1} Até :total clique no WhatsApp por mês|[2,*] Até :total cliques no WhatsApp por mês',
    'total_whatsapp_auto' => '{1} Até :total aviso automático por WhatsApp por mês (confirmação, lembrete e link da sala)|[2,*] Até :total avisos automáticos por WhatsApp por mês (confirmação, lembrete e link da sala)',
    'total_leads' => '{1} Receba até :total novo paciente qualificado pelo sistema|[2,*] Receba até :total novos pacientes qualificados pelo sistema',
    'payment_fee_pix' => 'Taxa de :percent no pagamento confirmado via Pix',
    'payment_fee_card' => 'Taxa de :percent no pagamento confirmado via cartão de crédito',
    'payment_fee_mercado_pago_only' => '(Taxas apenas do Mercado Pago)',
    'model_ia' => 'ChatGPT 5',

    'agenda_unlimited' => 'Agenda ilimitada',
    'patients_unlimited' => 'Pacientes ilimitados',
    'medical_records_unlimited' => 'Prontuários ilimitados',
    'scales_unlimited' => 'Aplicações de escalas ilimitadas',
    'payment_orders_unlimited' => 'Cobranças ilimitadas',
    'documents_issued_unlimited' => 'Emissão de documentos',
    'whatsapp_manual_unlimited' => 'Cliques ilimitados para WhatsApp',
    'whatsapp_auto_unlimited' => 'Avisos automáticos por WhatsApp ilimitados (confirmação, lembrete e link da sala)',
    'leads_unlimited' => 'Receba novos pacientes qualificados pelo sistema',

    'taglines' => [
        'free' => 'Para começar com calma, grátis para sempre',
        'professional' => 'Automação para o consultório trabalhar por você',
    ],

    'features' => [
        'video_calls' => 'Vídeochamadas ilimitadas, com sala criptografada',
        'reports' => 'Relatórios do consultório',
        'health_receipts_batch' => 'Recibos do Receita Saúde em lote, prontos para o Carnê-Leão',
        'ai_medical_records' => 'IA que ajuda você a escrever prontuários com mais agilidade (:model)',
        'public_booking' => 'Link simples para seus pacientes agendarem consultas sem precisar entrar em contato',
        'automatic_billing' => 'Envio automático de cobranças, reduzindo a inadimplência',
    ],

    'plan_not_found' => 'Plano inválido.',
    'cycle_invalid' => 'Periodicidade de cobrança inválida.',
    'payment_method_invalid' => 'Forma de pagamento inválida.',
    'annual_not_available' => 'O plano anual não está disponível para este plano.',
    'plan_change_unavailable' => 'Você já possui este plano. A renovação ficará disponível próximo ao vencimento.',
    // Limite mensal atingido (PlanLimitService), por chave de limite do config
    'limit_reached' => [
        'total_agenda' => 'Você usou todos os agendamentos do plano Grátis deste mês. O saldo volta no dia 1º. Para agendar agora, faça o upgrade para o Profissional.',
        'total_medical_records' => 'Você usou todos os prontuários do plano Grátis deste mês. O saldo volta no dia 1º. Para registrar agora, faça o upgrade para o Profissional.',
        'total_scales' => 'Você usou todas as aplicações de escalas do plano Grátis deste mês. O saldo volta no dia 1º. Para aplicar agora, faça o upgrade para o Profissional.',
        'total_payment_orders' => 'Você usou todas as cobranças do plano Grátis deste mês. O saldo volta no dia 1º. Para cobrar agora, faça o upgrade para o Profissional.',
        'total_documents_issued' => 'A emissão de documentos está disponível no plano Profissional. Faça o upgrade para emitir.',
    ],
    // Cota mensal de IA esgotada (AiUsageService), por plano
    'ai_limit_reached' => [
        'free' => 'Você usou todo o limite de IA do plano Grátis deste mês. O saldo volta no dia 1º. Para usar agora, faça o upgrade para o Profissional.',
        'professional' => 'Você usou todo o limite de IA deste mês. O saldo volta no dia 1º.',
        'not_included' => 'A IA no prontuário está disponível no plano Profissional. Faça o upgrade para usar.',
    ],
    // Nomes no aviso de saldo do painel
    'usage_labels' => [
        'total_agenda' => 'agendamentos',
        'total_medical_records' => 'prontuários',
        'total_documents_issued' => 'documentos emitidos',
        'total_scales' => 'aplicações de escalas',
        'total_payment_orders' => 'cobranças',
    ],
    'renewal_mail_subject' => 'Seu plano anual vence em breve',
    'plan_free_no_checkout' => 'O plano gratuito não requer pagamento.',
    'checkout_failed' => 'Não foi possível iniciar a assinatura no Mercado Pago. Tente novamente.',
    'subscription_not_found' => 'Assinatura não encontrada.',
    'subscription_already_pending' => 'Você já tem uma assinatura em andamento. Aguarde a confirmação do Mercado Pago antes de assinar novamente.',
    'subscription_not_found_for_preapproval' => 'Assinatura não encontrada para a notificação recebida do Mercado Pago.',
    'cancel_renewal_unavailable' => 'Você não possui uma assinatura ativa para cancelar a renovação.',
    'cancel_renewal_already_scheduled' => 'A renovação do seu plano já foi cancelada.',
    'cancel_renewal_failed' => 'Não foi possível cancelar a renovação. Tente novamente.',
    'cancel_renewal_success' => 'Renovação cancelada. Seu plano continua ativo até :date e depois sua conta volta para o plano Grátis, com todos os seus registros.',
    'cancel_renewal_blocked' => 'A renovação do seu plano foi cancelada. Você poderá assinar novamente após o vencimento.',
    'cancellation_mail_subject' => 'Renovação do seu plano cancelada',
    'user_not_found' => 'Usuário não encontrado.',
    'user_update_failed' => 'Falha ao atualizar os dados do usuário.',
];
