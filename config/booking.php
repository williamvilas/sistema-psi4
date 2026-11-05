<?php

declare(strict_types=1);

return [
    // Quantos dias à frente o paciente pode agendar
    'horizon_days' => 30,
    // Quantos dias com horários livres são exibidos na página pública
    'max_days' => 8,
    // Antecedência mínima (em horas) para o agendamento
    'min_notice_hours' => 2,
    // Duração padrão da sessão (minutos) quando não há configuração
    'default_session_minutes' => 50,
];
