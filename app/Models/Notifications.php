<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationRecipientEnum;
use App\Enums\NotificationsStatusEnum;
use App\Enums\NotificationTypeEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\UniqueConstraintViolationException;

class Notifications extends Model
{
    use SoftDeletes;

    protected $table = 'notifications';

    // Consulta, paciente e psicóloga usados no envio e nas respostas do WhatsApp
    private const AGENDA_CONTEXT = [
        'agenda:id,user_id,patient_id,type,status,scheduled_on,room_link,deleted_at',
        'patient:id,name,phone,whatsapp_notifications,deleted_at',
        'user:id,name,whatsapp,timezone,current_plan',
    ];

    protected $fillable = [
        'user_id',
        'patient_id',
        'agenda_id',
        'recipient',
        'type',
        'channel',
        'dedupe_key',
        'provider_message_id',
        'status',
        'message',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'patient_id' => 'integer',
        'agenda_id' => 'integer',
        'recipient' => NotificationRecipientEnum::class,
        'type' => NotificationTypeEnum::class,
        'channel' => NotificationChannelEnum::class,
        'status' => NotificationsStatusEnum::class,
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patients::class)->withTrashed();
    }

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(Agenda::class)->withTrashed();
    }

    /**
     * Registra a notificação como pendente. Retorna null quando já existe uma ativa para
     * a mesma agenda + destinatário + tipo + canal (trava de idempotência em `dedupe_key`).
     */
    public function createPendingIfNotExists(
        int $userId,
        int $patientId,
        int $agendaId,
        NotificationRecipientEnum $recipient,
        NotificationTypeEnum $type,
        NotificationChannelEnum $channel,
        string $message,
    ): ?self {
        try {
            $notification = $this->newQuery()->firstOrCreate(
                ['dedupe_key' => self::dedupeKey($agendaId, $recipient, $type, $channel)],
                [
                    'user_id' => $userId,
                    'patient_id' => $patientId,
                    'agenda_id' => $agendaId,
                    'recipient' => $recipient->value,
                    'type' => $type->value,
                    'channel' => $channel->value,
                    'status' => NotificationsStatusEnum::PENDING->value,
                    'message' => $message,
                ]
            );
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        return $notification->wasRecentlyCreated ? $notification : null;
    }

    public static function dedupeKey(int $agendaId, NotificationRecipientEnum $recipient, NotificationTypeEnum $type, NotificationChannelEnum $channel): string
    {
        return "{$agendaId}:{$recipient->value}:{$type->value}:{$channel->value}";
    }

    /**
     * Libera a trava de idempotência dos avisos da agenda (consulta remarcada ou horário cancelado reaproveitado),
     * mantendo o histórico: os próximos avisos da mesma agenda voltam a ser registrados.
     */
    public function releaseByAgenda(int $agendaId): int
    {
        return $this->newQuery()
            ->where('agenda_id', $agendaId)
            ->whereNotNull('dedupe_key')
            ->update(['dedupe_key' => null]);
    }

    public function findByIdWithAgendaContext(int $id): ?self
    {
        return $this->newQuery()->with(self::AGENDA_CONTEXT)->find($id);
    }

    public function findByProviderMessageIdWithAgendaContext(string $providerMessageId): ?self
    {
        return $this->newQuery()->with(self::AGENDA_CONTEXT)->where('provider_message_id', $providerMessageId)->first();
    }

    public function findByProviderMessageId(string $providerMessageId): ?self
    {
        return $this->newQuery()->where('provider_message_id', $providerMessageId)->first();
    }

    /**
     * Trava o envio: só uma execução do job passa de pendente para enviando.
     */
    public function markProcessing(int $id): bool
    {
        return $this->newQuery()
            ->whereKey($id)
            ->where('status', NotificationsStatusEnum::PENDING->value)
            ->update(['status' => NotificationsStatusEnum::PROCESSING->value]) > 0;
    }

    public function markPending(int $id): void
    {
        $this->newQuery()->whereKey($id)->update(['status' => NotificationsStatusEnum::PENDING->value]);
    }

    public function markDone(int $id, string $providerMessageId): void
    {
        $this->newQuery()->whereKey($id)->update([
            'status' => NotificationsStatusEnum::DONE->value,
            'provider_message_id' => $providerMessageId,
            'error' => null,
            'sent_at' => now(),
        ]);
    }

    public function markError(int $id, string $error): void
    {
        $this->newQuery()->whereKey($id)->update([
            'status' => NotificationsStatusEnum::ERROR->value,
            'error' => $error,
        ]);
    }

    /**
     * Mensagens enviadas por WhatsApp pela psicóloga no período (consumo do limite mensal do plano).
     */
    public function countSentByChannelBetween(int $userId, NotificationChannelEnum $channel, Carbon $startUtc, Carbon $endUtc): int
    {
        return $this->newQuery()
            ->where('user_id', $userId)
            ->where('channel', $channel->value)
            ->where('status', NotificationsStatusEnum::DONE->value)
            ->whereBetween('sent_at', [$startUtc, $endUtc])
            ->count();
    }
}
