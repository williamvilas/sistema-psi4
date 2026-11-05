<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum FeatureUsageEnum: string
{
    use EnumTrait;

    case AGENDA = 'agenda';
    case PATIENTS = 'patients';
    case MEDICAL_RECORDS = 'medical_records';
    case DOCUMENTS = 'documents';
    case CREATE_PAYMENT_ORDER = 'create_payment_order';
    case PUBLIC_BOOKING = 'public_booking';
    case SEARCH_REPORT = 'search_report';
    case HELP_WHATSAPP = 'help_whatsapp';
    case CLICK_WHATSAPP = 'click_whatsapp';
    case SCALE_APPLICATIONS = 'scale_applications';
    case SCALE_LINKS = 'scale_links';
    case TRANSCRIPTION_MINUTES = 'transcription_minutes';
    // Gravado pelo microserviço de integrações (Go) a cada resposta da IA
    case AI_TOKENS = 'ai_tokens';
}
