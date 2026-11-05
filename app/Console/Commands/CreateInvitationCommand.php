<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RegistrationLeads;
use App\Services\RegistrationInvitationService;
use App\Services\WhatsAppLinkService;
use Illuminate\Console\Command;

class CreateInvitationCommand extends Command
{
    protected $signature = 'app:create-invitation-command {lead? : ID do pedido de convite} {--source= : Origem do convite (ex.: supervisora parceira)}';
    protected $description = 'Gera um convite de uso único e, se houver pedido, a mensagem pronta de WhatsApp.';

    public function handle(RegistrationInvitationService $service, RegistrationLeads $leads, WhatsAppLinkService $whatsApp): int
    {
        $lead = $this->argument('lead') ? $leads->findById((int) $this->argument('lead')) : null;

        if ($this->argument('lead') && ! $lead) {
            $this->error('Pedido de convite não encontrado.');

            return self::FAILURE;
        }

        $created = $service->create($lead?->id, $this->option('source') ?: null);
        $link = route('register', ['convite' => $created['token']]);

        $this->info('Convite gerado (válido até '.$created['invitation']->expires_at->format('d/m/Y').'):');
        $this->line($link);

        if ($lead) {
            $message = __('invitations.whatsapp_message', ['name' => explode(' ', $lead->name)[0], 'link' => $link]);
            $this->newLine();
            $this->info("Enviar para {$lead->name}:");
            $this->line($whatsApp->toNumber($lead->whatsapp, $message));
        }

        return self::SUCCESS;
    }
}
