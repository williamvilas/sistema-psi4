<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

/**
 * Espelha o `status` de uma assinatura (`/preapproval`) do Mercado Pago.
 */
enum PlanSubscriptionStatusEnum: string
{
    use EnumTrait;

    case PENDING = 'pending';
    case AUTHORIZED = 'authorized';
    case PAUSED = 'paused';
    case CANCELLED = 'cancelled';

    public function getText(): string
    {
        return __('enum.plan_subscription_status.'.$this->value);
    }
}
