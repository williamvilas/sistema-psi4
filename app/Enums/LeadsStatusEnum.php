<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum LeadsStatusEnum: string
{
    use EnumTrait;

    case NEW = 'new';
    case FIRST_CONTACT = 'first_contact';
    case AWAITING_RESPONSE = 'awaiting_response';
    case IN_CONVERSATION = 'in_conversation';
    case SCHEDULING_PENDING = 'scheduling_pending';
    case SESSION_SCHEDULED = 'session_scheduled';
    case FIRST_SESSION_COMPLETED = 'first_session_completed';
    case IN_FOLLOW_UP = 'in_follow_up';
    case INACTIVE = 'inactive';
    case CLOSED = 'closed';
    case CONVERTED_TO_PATIENT = 'converted_to_patient';
}
