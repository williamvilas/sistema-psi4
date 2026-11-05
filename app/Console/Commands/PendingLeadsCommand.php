<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RegistrationLeads;
use Illuminate\Console\Command;

class PendingLeadsCommand extends Command
{
    protected $signature = 'app:pending-leads-command';
    protected $description = 'Lista os pedidos de convite que ainda não receberam convite.';

    public function handle(RegistrationLeads $leads): int
    {
        $pending = $leads->getPending();

        if ($pending->isEmpty()) {
            $this->info('Nenhum pedido de convite pendente.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Nome', 'WhatsApp', 'Status', 'Pedido em'],
            $pending->map(fn ($l) => [$l->id, $l->name, $l->whatsapp, $l->status, $l->created_at->format('d/m/Y H:i')])->all(),
        );

        return self::SUCCESS;
    }
}
