<?php

declare(strict_types=1);

return [
    'not_found' => 'Recibo não encontrado.',
    'patient_not_found' => 'Paciente não encontrado.',
    'batch_not_found' => 'Arquivo não encontrado.',
    'cannot_edit' => 'Só é possível alterar recebimentos registrados por você que ainda não foram para um arquivo.',
    'cannot_delete' => 'Só é possível apagar recebimentos registrados por você que ainda não foram para um arquivo. Para uma cobrança paga pelo PSI4, use "Não emitir".',
    'cannot_skip' => 'Só é possível deixar de emitir um recibo que ainda não foi para um arquivo.',
    'cannot_restore' => 'Este recibo não pode voltar para a lista de emissão.',
    'professional_without_document' => 'Informe seu CPF no seu perfil antes de gerar o arquivo. Ele precisa ser o mesmo CPF que você usa no Carnê-Leão.',
    'different_years' => 'O arquivo do Carnê-Leão só aceita pagamentos de um mesmo ano. Escolha um período dentro de um único ano.',
    'nothing_to_export' => 'Não há recibos prontos para emitir neste período.',
    'cannot_mark_imported' => 'Este arquivo já foi marcado como importado ou foi desfeito.',
    'cannot_undo' => 'Este arquivo já foi desfeito.',
    'default_description' => 'Atendimento psicológico',

    'stored_success' => 'Recebimento registrado.',
    'updated_success' => 'Recebimento atualizado.',
    'deleted_success' => 'Recebimento apagado.',
    'skipped_success' => 'Recibo marcado para não emitir.',
    'restored_success' => 'Recibo de volta à lista de emissão.',
    'imported_success' => 'Arquivo marcado como importado no e-CAC.',
    'undone_success' => 'Arquivo desfeito. Os recibos voltaram para a lista de emissão.',

    'issues' => [
        'patient_without_document' => 'Paciente sem CPF',
        'patient_invalid_document' => 'CPF do paciente inválido',
        'payer_invalid_document' => 'CPF do responsável financeiro inválido',
        'invalid_amount' => 'Valor precisa ser maior que zero',
    ],
];
