<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\NumberHelper;
use App\Models\Agenda;
use App\Models\Notifications;
use App\Models\Patients;
use App\Wrappers\WhatsAppCloudWrapper;
use Illuminate\Support\Str;

/**
 * Processa o webhook da API do WhatsApp: status de entrega das mensagens e respostas dos pacientes
 * (botões do lembrete e pedido para sair dos avisos).
 */
readonly class WhatsAppWebhookService
{
    public function __construct(
        private Notifications $notifications,
        private Agenda $agenda,
        private Patients $patients,
        private NotificationService $notificationService,
        private WhatsAppCloudWrapper $whatsApp,
    ) {
    }

    public function process(array $payload): void
    {
        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (($change['field'] ?? null) !== 'messages') {
                    continue;
                }

                foreach ((array) ($change['value']['statuses'] ?? []) as $status) {
                    $this->handleStatus((array) $status);
                }

                foreach ((array) ($change['value']['messages'] ?? []) as $message) {
                    $this->handleMessage((array) $message);
                }
            }
        }
    }

    /**
     * Só a falha muda a notificação: enviada, entregue e lida continuam como enviada.
     */
    private function handleStatus(array $status): void
    {
        if (($status['status'] ?? null) !== 'failed' || empty($status['id'])) {
            return;
        }

        $notification = $this->notifications->findByProviderMessageId((string) $status['id']);

        if (! $notification) {
            return;
        }

        $error = $status['errors'][0] ?? [];
        $detail = trim(($error['code'] ?? '').' '.($error['title'] ?? ''));

        $this->notifications->markError(
            $notification->id,
            Str::limit(__('notifications.whatsapp_rejected').($detail !== '' ? " ({$detail})" : ''), 1000),
        );
    }

    private function handleMessage(array $message): void
    {
        $from = (string) ($message['from'] ?? '');

        if ($from === '') {
            return;
        }

        $reply = match ($message['type'] ?? null) {
            'button' => $this->handleButton((string) ($message['button']['payload'] ?? ''), (string) ($message['context']['id'] ?? '')),
            'text' => $this->handleText($from, (string) ($message['text']['body'] ?? '')),
            default => __('notifications.reply.default'),
        };

        $this->reply($from, $reply);
    }

    /**
     * Botões do lembrete. A mensagem respondida (context.id) identifica a consulta: só quem recebeu o lembrete responde a ele.
     */
    private function handleButton(string $payload, string $contextId): string
    {
        $notification = $contextId !== '' ? $this->notifications->findByProviderMessageIdWithAgendaContext($contextId) : null;

        $agenda = $notification?->agenda;

        if (! $agenda || ! $notification->user) {
            return __('notifications.reply.default');
        }

        $psychologist = trim((string) $notification->user->name);

        if ($agenda->trashed() || ! $agenda->status->isOpen() || $agenda->scheduled_on->isPast()) {
            return __('notifications.reply.inactive', ['psychologist' => $psychologist]);
        }

        return match ($payload) {
            config('whatsapp.buttons.confirm') => $this->confirm($agenda->id),
            config('whatsapp.buttons.reschedule') => $this->requestReschedule($agenda->id, $psychologist),
            default => __('notifications.reply.default'),
        };
    }

    private function confirm(int $agendaId): string
    {
        // Já confirmada (toque repetido no botão) também responde como confirmada
        $this->agenda->confirmByPatient($agendaId);

        return __('notifications.reply.confirmed');
    }

    private function requestReschedule(int $agendaId, string $psychologist): string
    {
        $this->notificationService->notifyRescheduleRequest($agendaId);

        return __('notifications.reply.reschedule', ['psychologist' => $psychologist]);
    }

    private function handleText(string $from, string $body): string
    {
        $word = Str::upper(trim($body));

        if (! in_array($word, (array) config('whatsapp.opt_out_words'), true)) {
            return __('notifications.reply.default');
        }

        $this->patients->disableWhatsAppNotificationsByPhone(NumberHelper::fromWhatsAppNumber($from));

        return __('notifications.reply.opt_out');
    }

    /**
     * Resposta livre (gratuita dentro da janela de 24h aberta pelo paciente). A falha já é registrada pelo
     * wrapper e não deve reprocessar o webhook inteiro (confirmaria ou responderia de novo).
     */
    private function reply(string $to, string $body): void
    {
        try {
            $this->whatsApp->sendText($to, $body);
        } catch (\Throwable) {
            return;
        }
    }
}
