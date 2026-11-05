<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AgendaStatusEnum;
use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationRecipientEnum;
use App\Enums\NotificationsStatusEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\Agenda;
use App\Models\Notifications;
use App\Models\Patients;
use App\Models\User;
use App\Services\WhatsAppWebhookService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppWebhookServiceTest extends TestCase
{
    use DatabaseTransactions;

    private WhatsAppWebhookService $service;
    private User $user;
    private Agenda $agenda;
    private Notifications $reminder;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.REPLY']]])]);

        config([
            'whatsapp.access_token' => 'token',
            'whatsapp.phone_number_id' => '123456',
        ]);

        $this->service = app(WhatsAppWebhookService::class);
        $this->user = User::factory()->create(['name' => 'Mariana Souza', 'whatsapp' => '11988887777']);

        $patient = Patients::create(['user_id' => $this->user->id, 'name' => 'Maria Paciente', 'phone' => '11999998888']);

        $this->agenda = Agenda::create([
            'user_id' => $this->user->id,
            'patient_id' => $patient->id,
            'type' => 'online',
            'scheduled_on' => now('UTC')->addDay()->startOfHour(),
            'ends_at' => now('UTC')->addDay()->startOfHour()->addHour(),
            'status' => AgendaStatusEnum::SCHEDULED->value,
        ]);

        $this->reminder = Notifications::create([
            'user_id' => $this->user->id,
            'patient_id' => $patient->id,
            'agenda_id' => $this->agenda->id,
            'recipient' => NotificationRecipientEnum::PATIENT->value,
            'type' => NotificationTypeEnum::AGENDA_REMINDER->value,
            'channel' => NotificationChannelEnum::WHATSAPP->value,
            'status' => NotificationsStatusEnum::DONE->value,
            'provider_message_id' => 'wamid.REMINDER-'.uniqid(),
            'message' => 'lembrete',
        ]);
    }

    private function payload(array $value): array
    {
        return ['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['field' => 'messages', 'value' => $value]]]]];
    }

    private function button(string $payload, ?string $contextId = null): array
    {
        return $this->payload(['messages' => [[
            'from' => '5511999998888',
            'type' => 'button',
            'button' => ['payload' => $payload, 'text' => 'Botão'],
            'context' => ['id' => $contextId ?? $this->reminder->provider_message_id],
        ]]]);
    }

    private function assertRepliedWith(string $text): void
    {
        Http::assertSent(fn (Request $request) => $request['type'] === 'text'
            && $request['to'] === '5511999998888'
            && $request['text']['body'] === $text);
    }

    public function testItConfirmAgendaWhenPatientTapsConfirm(): void
    {
        $this->service->process($this->button('confirm'));

        $this->assertSame(AgendaStatusEnum::CONFIRMED, $this->agenda->fresh()->status);
        $this->assertRepliedWith(__('notifications.reply.confirmed'));
    }

    public function testItNotifyPsychologistWhenPatientAsksToReschedule(): void
    {
        $this->service->process($this->button('reschedule'));

        $this->assertSame(AgendaStatusEnum::SCHEDULED, $this->agenda->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'agenda_id' => $this->agenda->id,
            'recipient' => NotificationRecipientEnum::PSYCHOLOGIST->value,
            'type' => NotificationTypeEnum::AGENDA_RESCHEDULE_REQUEST->value,
        ]);
        $this->assertRepliedWith(__('notifications.reply.reschedule', ['psychologist' => 'Mariana Souza']));
    }

    public function testItReplyInactiveWhenAgendaWasCanceled(): void
    {
        $this->agenda->update(['status' => AgendaStatusEnum::CANCELED->value]);

        $this->service->process($this->button('confirm'));

        $this->assertSame(AgendaStatusEnum::CANCELED, $this->agenda->fresh()->status);
        $this->assertRepliedWith(__('notifications.reply.inactive', ['psychologist' => 'Mariana Souza']));
    }

    public function testItReplyDefaultWhenButtonContextIsUnknown(): void
    {
        $this->service->process($this->button('confirm', 'wamid.DESCONHECIDO'));

        $this->assertSame(AgendaStatusEnum::SCHEDULED, $this->agenda->fresh()->status);
        $this->assertRepliedWith(__('notifications.reply.default'));
    }

    public function testItOptOutPatientWhenRepliesSair(): void
    {
        // O WhatsApp devolve o celular sem o nono dígito
        $this->service->process($this->payload(['messages' => [[
            'from' => '551199998888',
            'type' => 'text',
            'text' => ['body' => ' sair '],
        ]]]));

        $this->assertFalse(Patients::find($this->agenda->patient_id)->whatsapp_notifications);
        Http::assertSent(fn (Request $request) => $request['text']['body'] === __('notifications.reply.opt_out'));
    }

    public function testItReplyDefaultToOtherMessages(): void
    {
        $this->service->process($this->payload(['messages' => [[
            'from' => '5511999998888',
            'type' => 'text',
            'text' => ['body' => 'Oi, posso mudar o horário?'],
        ]]]));

        $this->assertTrue(Patients::find($this->agenda->patient_id)->whatsapp_notifications);
        $this->assertRepliedWith(__('notifications.reply.default'));
    }

    public function testItMarkNotificationErrorWhenDeliveryFails(): void
    {
        $this->service->process($this->payload(['statuses' => [[
            'id' => $this->reminder->provider_message_id,
            'status' => 'failed',
            'errors' => [['code' => 131026, 'title' => 'Message undeliverable']],
        ]]]));

        $reminder = $this->reminder->fresh();
        $this->assertSame(NotificationsStatusEnum::ERROR, $reminder->status);
        $this->assertStringContainsString('131026', $reminder->error);
        Http::assertNothingSent();
    }

    public function testItIgnoreDeliveredAndReadStatuses(): void
    {
        $this->service->process($this->payload(['statuses' => [
            ['id' => $this->reminder->provider_message_id, 'status' => 'delivered'],
            ['id' => $this->reminder->provider_message_id, 'status' => 'read'],
        ]]));

        $this->assertSame(NotificationsStatusEnum::DONE, $this->reminder->fresh()->status);
    }

    public function testItKeepProcessingWhenReplyFails(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => 'boom'], 500)]);

        $this->service->process($this->button('confirm'));

        $this->assertSame(AgendaStatusEnum::CONFIRMED, $this->agenda->fresh()->status);
    }
}
