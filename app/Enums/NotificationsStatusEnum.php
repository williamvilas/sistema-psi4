<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum NotificationsStatusEnum: string
{
    use EnumTrait;

    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case ERROR = 'error';
    case DONE = 'done';

    public function getText(): string
    {
        return __('enum.notification_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'bg-warning',
            self::PROCESSING => 'bg-primary',
            self::ERROR => 'bg-danger',
            self::DONE => 'bg-success',
        };
    }

    public static function getTexts(): array
    {
        $texts = [];

        foreach (self::cases() as $case) {
            $texts[$case->value] = $case->getText();
        }

        return $texts;
    }
}
