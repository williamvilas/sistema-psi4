<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum NotificationChannelEnum: string
{
    use EnumTrait;

    case WHATSAPP = 'whatsapp';
    case SMS = 'sms';
    case EMAIL = 'email';

    public function getText(): string
    {
        return __('enum.notification_channel.'.$this->value);
    }
}
