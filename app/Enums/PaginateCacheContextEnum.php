<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum PaginateCacheContextEnum: string
{
    use EnumTrait;

    case AGENDA = 'agenda';
    case PATIENTS = 'patients';
    case MEDICAL_RECORDS = 'medical_records';
    case DOCUMENTS = 'documents';
    case PAYMENTS = 'payments';

    /**
     * Agenda e pagamentos dependem do relógio (ex.: "a partir de agora", "vencido"),
     * então o TTL é curto como rede de segurança.
     */
    public function ttlMinutes(): int
    {
        return match ($this) {
            self::AGENDA, self::PAYMENTS => 5,
            default => 30,
        };
    }

    /**
     * @return self[] listagens que exibem dados do paciente (nome/telefone) e precisam ser
     *                invalidadas quando o paciente muda
     */
    public static function patientDependents(): array
    {
        return [self::PATIENTS, self::AGENDA, self::MEDICAL_RECORDS, self::PAYMENTS];
    }
}
