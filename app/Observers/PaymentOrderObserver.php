<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\PaginateCacheContextEnum;
use App\Helpers\DashboardCache;
use App\Helpers\PaginateCache;
use App\Models\PaymentOrder;

class PaymentOrderObserver
{
    public function created(PaymentOrder $paymentOrder): void
    {
        $this->forget($paymentOrder);
    }

    public function updated(PaymentOrder $paymentOrder): void
    {
        $this->forget($paymentOrder);
    }

    public function deleted(PaymentOrder $paymentOrder): void
    {
        $this->forget($paymentOrder);
    }

    public function restored(PaymentOrder $paymentOrder): void
    {
        $this->forget($paymentOrder);
    }

    public function forceDeleted(PaymentOrder $paymentOrder): void
    {
        $this->forget($paymentOrder);
    }

    private function forget(PaymentOrder $paymentOrder): void
    {
        // A ordem pode ter sido carregada sem a coluna user_id (select parcial)
        $userId = $paymentOrder->user_id
            ?? PaymentOrder::withTrashed()->whereKey($paymentOrder->getKey())->value('user_id');

        if ($userId) {
            DashboardCache::forget((int) $userId);
            PaginateCache::forget((int) $userId, PaginateCacheContextEnum::PAYMENTS);
        }
    }
}
