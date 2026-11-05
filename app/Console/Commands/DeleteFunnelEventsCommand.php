<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FunnelEvent;
use Illuminate\Console\Command;

class DeleteFunnelEventsCommand extends Command
{
    protected $signature = 'app:delete-funnel-events-command {--months=12}';
    protected $description = 'Remove eventos de funil com mais de X meses';

    public function handle(FunnelEvent $funnelEvent): void
    {
        $total = $funnelEvent->deleteOlderThan(now()->subMonths((int) $this->option('months')));

        $this->info("Eventos removidos: {$total}");
    }
}
