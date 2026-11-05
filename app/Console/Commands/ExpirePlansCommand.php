<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RegisterLog;
use App\Services\PlanSubscriptionService;
use Illuminate\Console\Command;

class ExpirePlansCommand extends Command
{
    protected $signature = 'app:expire-plans-command';
    protected $description = 'Rebaixa para o plano grátis os usuários cujo plano anual venceu ou que cancelaram a renovação do mensal';

    public function handle(PlanSubscriptionService $planSubscriptionService): int
    {
        try {
            $total = $planSubscriptionService->expireAnnualPlans() + $planSubscriptionService->expireCancelledMonthlyPlans();

            $this->info($total.' plano(s) expirado(s).');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            RegisterLog::dispatchSync([
                'message' => 'Erro ao expirar planos: '.$e->getMessage(),
                'data' => ['trace' => $e->getTraceAsString()],
                'route' => 'app:expire-plans-command',
                'method' => 'COMMAND',
            ]);

            return self::FAILURE;
        }
    }
}
