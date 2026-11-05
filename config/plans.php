<?php

declare(strict_types=1);

return [
    // Limites mensais (-1 = ilimitado). O uso é contado em `user_feature_usages` e
    // aplicado pelo PlanLimitService: cancelar ou apagar não devolve o saldo.
    // Quando o Profissional acaba, a conta volta para o Grátis sem perder nada:
    // tudo o que já existe continua editável, só o que é novo entra no limite.
    'plans' => [
        'free' => [
            'total_agenda' => -1,
            'total_patients' => -1,
            'total_medical_records' => -1,
            'total_documents' => -1,
            // Emissão de documentos é do Profissional (0 = não incluso)
            'total_documents_issued' => 0,
            'total_scales' => -1,
            'total_transcription_minutes' => 120,
            // Tokens de IA por mês (assistente do prontuário). O microserviço de integrações grava o consumo
            // e o AiUsageService compara com este limite; virou o mês, o uso volta a zero
            'total_ai_tokens' => 20000,
            'total_payment_orders' => -1,
            'total_whatsapp_manual' => -1,
            // Avisos automáticos por WhatsApp custam na Meta: só no Profissional (0 = não incluso)
            'total_whatsapp_auto' => 0,
            // Taxa do PSI4 (%) sobre cada cobrança paga pelo Mercado Pago, somada à taxa do próprio Mercado Pago
            'payment_fee_percent' => 2,
            'price' => 0,
            'emphasis' => false,
            'call_action' => 'Criar conta grátis',
            'extras' => [
                'video_calls',
                'reports',
                'health_receipts_batch',
                'public_booking',
                'automatic_billing',
            ],
        ],

        'professional' => [
            'total_agenda' => -1,
            'total_patients' => -1,
            'total_medical_records' => -1,
            'total_documents' => -1,
            'total_documents_issued' => -1,
            'total_scales' => -1,
            // Minutos de transcrição por mês. Custo por minuto na AWS: sem cota, a função dá prejuízo
            'total_transcription_minutes' => 600,
            // Tokens de IA por mês. Custo por token no provedor: sem cota, a função dá prejuízo
            'total_ai_tokens' => 300000,
            'total_payment_orders' => -1,
            'total_whatsapp_manual' => -1,
            // Avisos automáticos por WhatsApp (confirmação, lembrete e link da sala) por mês; cada um custa na Meta
            'total_whatsapp_auto' => 400,
            'total_leads' => -1,
            // Sem taxa do PSI4 nas cobranças: só a taxa do próprio Mercado Pago
            'payment_fee_percent' => 0,
            'price' => 69.90,
            // Plano anual: pagamento único (sem renovação automática), com fidelidade
            // de 12 meses a `monthly_price` por mês. `pix_discount` é um desconto
            // adicional, aplicado sobre o total anual, para pagamento à vista no PIX.
            'annual' => [
                'monthly_price' => 49,
                'pix_discount' => 10,
                'installments' => 12,
                'days' => 365,
            ],
            'emphasis' => false,
            'call_action' => 'Criar conta grátis',
            // Slugs de `plans.features.*`: na tela de planos, os planos que não
            // têm o recurso o exibem como "não incluso"
            'extras' => [
                //'Confirmações via WhatsApp',
                //'Recebedor de Leads',
                //'Validador de Documentos Emitidos',
                'video_calls',
                'reports',
                'health_receipts_batch',
                'ai_medical_records',
                'public_booking',
                'automatic_billing',
                //'Cobrança automatíca via WhatsApp'
                //'Envie prévias por whatsapp, recibos ou demonstrativos de pagamentos anuais para declaração de IR com praticidade'
            ],
        ],
    ],
];
