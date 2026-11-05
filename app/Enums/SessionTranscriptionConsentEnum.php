<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum SessionTranscriptionConsentEnum: string
{
    use EnumTrait;

    case ACCEPTED = 'accepted';
    case REFUSED = 'refused';
    case REVOKED = 'revoked';
}
