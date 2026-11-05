<?php

declare(strict_types=1);

return [
    'patient_not_found' => 'Paciente não encontrado.',
    'not_found' => 'Ordem de pagamento não encontrada.',
    'cannot_edit' => 'Esta cobrança não pode ser editada, pois não está mais pendente ou o link de pagamento ainda está sendo gerado.',
    'cannot_refund' => 'Só é possível estornar ordens de pagamento já pagas.',
    'refund_rejected' => 'O Mercado Pago não permitiu o estorno deste pagamento.',
    'refund_failed' => 'Não foi possível estornar o pagamento agora. Tente novamente em instantes.',
    'refund_success' => 'Pagamento estornado com sucesso.',
    'integration_not_configured' => 'A integração de pagamentos não está configurada.',
    'integration_rejected' => 'A conta de pagamento foi recusada pela integração.',
    'order_rejected' => 'A ordem de pagamento foi recusada pela integração.',
    'order_update_rejected' => 'O Mercado Pago não aceitou as alterações desta cobrança. Confira os dados e tente novamente.',
    'integrated_edit_notice' => 'As alterações também serão aplicadas no Mercado Pago, e o link de pagamento já enviado ao paciente continua o mesmo. Se o paciente já gerou um Pix ou boleto com os dados anteriores, esse código continua válido.',
    'patient_without_email' => 'O paciente precisa ter um e-mail para gerar a cobrança.',
    'item_name' => 'Atendimento',
    'item_title' => 'Atendimento com :name',
    'item_description' => 'Ordem de pagamento :id',
    'whatsapp_message' => 'Olá, :name! Segue o link para o pagamento de :amount: :link',

    'account_not_connected' => 'Conecte sua conta do Mercado Pago antes de gerar cobranças para seus pacientes.',
    'account_needs_reauth' => 'Sua conexão com o Mercado Pago expirou. Reconecte para continuar recebendo pagamentos.',
    'oauth_state_invalid' => 'Não foi possível validar a conexão com o Mercado Pago. Tente novamente.',
    'oauth_denied' => 'A autorização com o Mercado Pago foi cancelada.',
    'oauth_failed' => 'Não foi possível conectar sua conta do Mercado Pago. Tente novamente.',
    'webhook_missing_data_id' => 'Notificação do Mercado Pago recebida sem identificador do recurso.',

    'fees' => [
        'title' => 'Taxas do Mercado Pago',
        'pix' => 'Pix',
        'card' => 'Cartão de crédito à vista',
        'installments' => 'Cartão de crédito de :fromx a :tox',
        'term_now' => 'recebe na hora',
        'term_days' => 'recebe em :days dias',
        'patient_interest' => 'Nas parcelas, a tabela vale quando você assume os juros. Se o paciente assumir, a taxa pode chegar a :percent.',
        'note' => 'Valores padrão conferidos em :date. Quem desconta é o Mercado Pago, e a taxa da sua conta pode ser diferente.',
        'psi4' => 'No plano Grátis, o PSI4 cobra mais :percent sobre cada cobrança paga. No Profissional, não há taxa do PSI4.',
    ],
];
