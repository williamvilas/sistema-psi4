<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RegisterLog;
use App\Services\PlanSubscriptionService;
use Illuminate\Console\Command;

class NotifyPlanRenewalCommand extends Command
{
    protected $signature = 'app:notify-plan-renewal-command';
    protected $description = 'Avisa por e-mail os usuários com plano anual perto de vencer (15, 3 e 0 dias antes)';

    public function handle(PlanSubscriptionService $planSubscriptionService): int
    {
        try {
            $total = $planSubscriptionService->notifyAnnualRenewals();

            $this->info($total.' aviso(s) de renovação enviado(s).');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            RegisterLog::dispatchSync([
                'message' => 'Erro ao avisar renovação de planos anuais: '.$e->getMessage(),
                'data' => ['trace' => $e->getTraceAsString()],
                'route' => 'app:notify-plan-renewal-command',
                'method' => 'COMMAND',
            ]);

            return self::FAILURE;
        }
    }
}
