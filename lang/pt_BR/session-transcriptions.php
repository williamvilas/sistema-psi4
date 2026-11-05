<?php

declare(strict_types=1);

return [
    'unavailable' => 'A transcrição não está disponível no momento.',
    'no_agenda' => 'A transcrição só está disponível em salas vinculadas a uma consulta.',
    'not_activated' => 'Para transcrever, ative as dinâmicas no PSI4 uma vez. A chave usada para cifrar o rascunho é a mesma.',
    'minor' => 'A transcrição ainda não está disponível para pacientes menores de idade.',
    'already_denied' => 'Este pedido já foi recusado. A psicóloga pode enviar um novo.',
    'already_active' => 'Já existe uma transcrição em andamento nesta sala.',
    'adult_confirmation_required' => 'Confirme que o paciente é maior de idade e não é interditado.',
    'quota_reached' => 'Os minutos de transcrição do seu plano acabaram neste mês. O saldo volta no dia 1º.',
    'consent_missing' => 'Falta a autorização de quem está na sala.',
    'stopped' => 'Esta transcrição foi encerrada.',
    'cannot_save' => 'Não foi possível guardar a transcrição. Tente de novo.',
    'not_found' => 'Rascunho não encontrado.',
    'draft_failed' => 'Não foi possível gerar o rascunho desta sessão.',
    'draft_unavailable' => 'Este rascunho não está mais disponível. O texto segue na tela: copie o que precisar antes de sair.',
    'pending_banner' => '{1} Você tem :count rascunho de registro da sessão aguardando sua revisão.|[2,*] Você tem :count rascunhos de registro da sessão aguardando sua revisão.',
    'patient_banner' => '{1} Há :count rascunho de registro da sessão deste paciente aguardando sua revisão.|[2,*] Há :count rascunhos de registro da sessão deste paciente aguardando sua revisão.',
    'patient_removed' => 'Paciente removido',
    'usage' => 'Transcrição de sessões: :used de :limit minutos usados neste mês. O saldo volta no dia 1º.',
    'usage_unlimited' => 'Transcrição de sessões: :used minutos usados neste mês.',
    'status' => [
        'recording' => 'Em andamento',
        'processing' => 'Preparando o rascunho',
        'failed' => 'Não foi possível gerar o rascunho, áudio apagado',
        'pending_review' => 'Aguardando sua revisão',
        'accepted' => 'Salva no prontuário',
        'rejected' => 'Recusada por você, texto apagado',
        'refused' => 'Não autorizada pelo paciente',
        'revoked' => 'Revogada pelo paciente',
        'expired' => 'Prazo vencido, texto apagado',
        'discarded' => 'Nada foi gravado',
    ],
    'decision' => [
        'accepted' => 'Autorizou a transcrição',
        'refused' => 'Não autorizou a transcrição',
        'revoked' => 'Revogou a autorização',
    ],
    'reminder_mail_subject' => 'Você tem rascunhos de transcrição aguardando revisão',
];
