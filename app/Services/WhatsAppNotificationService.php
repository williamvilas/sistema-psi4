<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationRecipientEnum;
use App\Enums\NotificationsStatusEnum;
use App\Enums\NotificationTypeEnum;
use App\Helpers\NumberHelper;
use App\Models\Notifications;
use App\Models\User;
use App\Wrappers\WhatsAppCloudWrapper;

/**
 * Envia as notificações registradas pelo NotificationService pelo número oficial da PSI4.
 */
readonly class WhatsAppNotificationService
{
    public function __construct(
        private Notifications $notifications,
        private NotificationService $notificationService,
        private WhatsAppCloudWrapper $whatsApp,
    ) {
    }

    /**
     * Falhas definitivas (número sem WhatsApp, limite do plano, paciente que saiu dos avisos) ficam gravadas
     * como erro; falhas temporárias (rede, 5xx, 429) devolvem a notificação para pendente e são relançadas
     * para o job tentar de novo.
     *
     * @return bool true quando a mensagem foi enviada
     */
    public function send(int $notificationId): bool
    {
        $notification = $this->notifications->findByIdWithAgendaContext($notificationId);

        if (! $notification
            || $notification->status !== NotificationsStatusEnum::PENDING
            || $notification->channel !== NotificationChannelEnum::WHATSAPP
            || ! $notification->agenda || ! $notification->patient || ! $notification->user) {
            return false;
        }

        $template = config("whatsapp.templates.{$notification->recipient->value}.{$notification->type->value}");

        if (! config('whatsapp.enabled') || $template === null) {
            return false;
        }

        $refusal = $this->refusalReason($notification);

        if ($refusal !== null) {
            $this->notifications->markError($notification->id, $refusal);

            return false;
        }

        // Só uma execução envia: outra que chegue junto encontra a notificação já em envio
        if (! $this->notifications->markProcessing($notification->id)) {
            return false;
        }

        $agenda = $notification->agenda->setRelation('patient', $notification->patient)->setRelation('user', $notification->user);
        $params = $this->notificationService->messageParams($agenda, $notification->recipient);

        try {
            $messageId = $this->whatsApp->sendTemplate(
                to: NumberHelper::toWhatsAppNumber($this->phoneOf($notification)),
                template: $template['name'],
                language: (string) config('whatsapp.language'),
                params: array_map(fn (string $key) => $this->sanitizeParam($params[$key] ?? ''), $template['params']),
                buttons: array_map(fn (string $button) => (string) config("whatsapp.buttons.{$button}"), $template['buttons'] ?? []),
            );
        } catch (\DomainException $e) {
            $this->notifications->markError($notification->id, $e->getMessage());

            return false;
        } catch (\Throwable $e) {
            $this->notifications->markPending($notification->id);

            throw $e;
        }

        $this->notifications->markDone($notification->id, $messageId);

        return true;
    }

    /**
     * Esgotadas as tentativas do job, a notificação fica com erro (e não presa como pendente).
     */
    public function markFailed(int $notificationId): void
    {
        $this->notifications->markError($notificationId, __('notifications.whatsapp_rejected'));
    }

    private function refusalReason(Notifications $notification): ?string
    {
        if ($notification->recipient === NotificationRecipientEnum::PATIENT && $notification->patient->whatsapp_notifications === false) {
            return __('notifications.whatsapp_opted_out');
        }

        if ($this->phoneOf($notification) === '') {
            return __('notifications.whatsapp_rejected');
        }

        if ($notification->type === NotificationTypeEnum::AGENDA_ROOM_LINK && empty($notification->agenda->room_link)) {
            return __('notifications.whatsapp_room_link_missing');
        }

        if ((int) config('plans.plans.'.$notification->user->current_plan.'.total_whatsapp_auto', 0) === 0) {
            return __('notifications.whatsapp_plan_unavailable');
        }

        if ($this->monthlyLimitReached($notification->user)) {
            return __('notifications.whatsapp_limit_reached');
        }

        return null;
    }

    private function monthlyLimitReached(User $user): bool
    {
        $limit = (int) config('plans.plans.'.$user->current_plan.'.total_whatsapp_auto', 0);

        if ($limit < 0) {
            return false;
        }

        $sent = $this->notifications->countSentByChannelBetween(
            userId: $user->id,
            channel: NotificationChannelEnum::WHATSAPP,
            startUtc: now()->startOfMonth(),
            endUtc: now()->endOfMonth(),
        );

        return $sent >= $limit;
    }

    private function phoneOf(Notifications $notification): string
    {
        return (string) NumberHelper::onlyNumbers((string) ($notification->recipient === NotificationRecipientEnum::PATIENT
            ? $notification->patient->phone
            : $notification->user->whatsapp));
    }

    /**
     * A Meta recusa variáveis com quebra de linha, tabulação ou mais de 4 espaços seguidos.
     */
    private function sanitizeParam(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $value !== '' ? $value : '-';
    }
}
