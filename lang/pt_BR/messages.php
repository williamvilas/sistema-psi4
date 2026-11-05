<?php

declare(strict_types=1);

return [
    'title_success' => 'Finalizado!',
    'message_success' => 'As informações foram atualizadas com sucesso.',
    'empty' => 'Nenhum registro foi encontrado.',
    'message_deleted' => 'Registro apagado com sucesso.',
    'failed_update' => 'Não foi possível atualizar o registro. Por favor, tente novamente mais tarde.',
    'failed_delete' => 'Não foi possível apagar o registro. Por favor, tente novamente mais tarde.',
    'total_records' => 'Total de Registro|Total de Registros',

    'text_help' => 'Oi! %0AEstou entrando em contato pelo suporte do *PSI4*. %0APreciso de ajuda com o sistema. %0A%0A*Código de Suporte:* :code',

    // Mensagens exibidas após redirect com ?m=<chave> (components/message-updated.blade.php)
    'redirect' => [
        'created_success' => 'Registro criado com sucesso.',
        'updated_success' => 'Alterações salvas com sucesso.',
        'scheduled' => 'Agendamento criado com sucesso.',
    ],

    'sessions' => '{0} Nenhuma sessão|{1} :count sessão|[2,*] :count sessões',
];
