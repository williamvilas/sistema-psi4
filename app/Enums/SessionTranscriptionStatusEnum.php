<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum SessionTranscriptionStatusEnum: string
{
    use EnumTrait;

    case AWAITING_CONSENT = 'awaiting_consent';
    case RECORDING = 'recording';
    case PROCESSING = 'processing';
    case FAILED = 'failed';
    case PENDING_REVIEW = 'pending_review';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case REFUSED = 'refused';
    case REVOKED = 'revoked';
    case EXPIRED = 'expired';
    case DISCARDED = 'discarded';
}
