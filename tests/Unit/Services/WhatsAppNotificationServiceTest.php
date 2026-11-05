<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AgendaStatusEnum;
use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationRecipientEnum;
use App\Enums\NotificationsStatusEnum;
use App\Enums\NotificationTypeEnum;
use App\Jobs\RegisterLog;
use App\Models\Agenda;
use App\Models\Notifications;
use App\Models\Patients;
use App\Models\User;
use App\Services\WhatsAppNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppNotificationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private WhatsAppNotificationService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        config([
            'whatsapp.enabled' => true,
            'whatsapp.access_token' => 'token-secreto',
            'whatsapp.phone_number_id' => '123456',
        ]);

        $this->service = app(WhatsAppNotificationService::class);
        $this->user = User::factory()->create(['name' => 'Mariana Souza', 'whatsapp' => '11988887777']);
    }

    private function makeNotification(
        NotificationTypeEnum $type = NotificationTypeEnum::AGENDA_REMINDER,
        NotificationRecipientEnum $recipient = NotificationRecipientEnum::PATIENT,
        array $patient = [],
        int $daysAhead = 1,
    ): Notifications {
        $patient = Patients::create($patient + [
            'user_id' => $this->user->id,
            'name' => 'maria paciente',
            'phone' => '11999998888',
        ]);

        $agenda = Agenda::create([
            'user_id' => $this->user->id,
            'patient_id' => $patient->id,
            'type' => 'online',
            'scheduled_on' => now('UTC')->addDays($daysAhead)->startOfHour(),
            'ends_at' => now('UTC')->addDays($daysAhead)->startOfHour()->addHour(),
            'status' => AgendaStatusEnum::SCHEDULED->value,
            'room_link' => 'https://meet.example.com/abc',
        ]);

        return Notifications::create([
            'user_id' => $this->user->id,
            'patient_id' => $patient->id,
            'agenda_id' => $agenda->id,
            'recipient' => $recipient->value,
            'type' => $type->value,
            'channel' => NotificationChannelEnum::WHATSAPP->value,
            'status' => NotificationsStatusEnum::PENDING->value,
            'message' => 'mensagem',
        ]);
    }

    public function testItSendTemplateWithParamsAndButtons(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ABC']]])]);
        $notification = $this->makeNotification();

        $this->assertTrue($this->service->send($notification->id));

        $notification->refresh();
        $this->assertSame(NotificationsStatusEnum::DONE, $notification->status);
        $this->assertSame('wamid.ABC', $notification->provider_message_id);
        $this->assertNotNull($notification->sent_at);

        Http::assertSent(function (Request $request) {
            $template = $request['template'];

            return str_ends_with($request->url(), '/123456/messages')
                && $request->hasHeader('Authorization', 'Bearer token-secreto')
                && $request['to'] === '5511999998888'
                && $template['name'] === config('whatsapp.templates.patient.agenda_reminder.name')
                && $template['components'][0]['parameters'][0]['text'] === 'Maria'
                && $template['components'][0]['parameters'][1]['text'] === 'Mariana Souza'
                && $template['components'][1]['parameters'][0]['payload'] === 'confirm'
                && $template['components'][2]['parameters'][0]['payload'] === 'reschedule';
        });
    }

    public function testItSendRoomLinkToPsychologistWhatsApp(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.PSI']]])]);
        $notification = $this->makeNotification(NotificationTypeEnum::AGENDA_ROOM_LINK, NotificationRecipientEnum::PSYCHOLOGIST);

        $this->assertTrue($this->service->send($notification->id));

        Http::assertSent(fn (Request $request) => $request['to'] === '5511988887777'
            && $request['template']['components'][0]['parameters'][0]['text'] === 'maria paciente'
            && $request['template']['components'][0]['parameters'][1]['text'] === 'https://meet.example.com/abc'
            && count($request['template']['components']) === 1);
    }

    public function testItDoNotSendWhenDisabled(): void
    {
        config(['whatsapp.enabled' => false]);
        Http::fake();
        $notification = $this->makeNotification();

        $this->assertFalse($this->service->send($notification->id));

        Http::assertNothingSent();
        $this->assertSame(NotificationsStatusEnum::PENDING, $notification->fresh()->status);
    }

    public function testItDoNotSendTypeWithoutTemplate(): void
    {
        Http::fake();
        $notification = $this->makeNotification(NotificationTypeEnum::AGENDA_NO_SHOW);

        $this->assertFalse($this->service->send($notification->id));

        Http::assertNothingSent();
    }

    public function testItDoNotSendTwice(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ABC']]])]);
        $notification = $this->makeNotification();

        $this->service->send($notification->id);
        $this->assertFalse($this->service->send($notification->id));

        Http::assertSentCount(1);
    }

    public function testItMarkErrorWhenPatientOptedOut(): void
    {
        Http::fake();
        $notification = $this->makeNotification(patient: ['whatsapp_notifications' => false]);

        $this->assertFalse($this->service->send($notification->id));

        Http::assertNothingSent();
        $notification->refresh();
        $this->assertSame(NotificationsStatusEnum::ERROR, $notification->status);
        $this->assertSame(__('notifications.whatsapp_opted_out'), $notification->error);
    }

    public function testItDoNotSendForFreePlanAccount(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.FREE']]])]);
        $notification = $this->makeNotification();
        $this->user->update(['current_plan' => 'free']);

        $this->assertFalse($this->service->send($notification->id));

        Http::assertNothingSent();
        $this->assertSame(__('notifications.whatsapp_plan_unavailable'), $notification->fresh()->error);
    }

    public function testItMarkErrorWhenMonthlyLimitIsReached(): void
    {
        config(['plans.plans.professional.total_whatsapp_auto' => 1]);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ABC']]])]);
        $first = $this->makeNotification();
        $second = $this->makeNotification(patient: ['phone' => '11977776666'], daysAhead: 2);

        $this->assertTrue($this->service->send($first->id));
        $this->assertFalse($this->service->send($second->id));

        Http::assertSentCount(1);
        $this->assertSame(__('notifications.whatsapp_limit_reached'), $second->fresh()->error);
    }

    public function testItMarkErrorAndRegisterLogWhenWhatsAppRejects(): void
    {
        Bus::fake([RegisterLog::class]);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 131026]], 400)]);
        $notification = $this->makeNotification();

        $this->assertFalse($this->service->send($notification->id));

        $notification->refresh();
        $this->assertSame(NotificationsStatusEnum::ERROR, $notification->status);
        $this->assertSame(__('notifications.whatsapp_rejected'), $notification->error);

        Bus::assertDispatchedSync(RegisterLog::class, fn (RegisterLog $job) => str_contains($job->exception['message'], 'HTTP 400')
            && str_contains($job->exception['data']['curl'], 'Bearer ***')
            && ! str_contains($job->exception['data']['curl'], 'token-secreto')
            && $job->exception['data']['status'] === 400);
    }

    public function testItReturnToPendingAndRethrowOnServerError(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => 'boom'], 500)]);
        $notification = $this->makeNotification();

        try {
            $this->service->send($notification->id);
            $this->fail('A falha temporária deveria ser relançada para o job tentar de novo.');
        } catch (\Illuminate\Http\Client\RequestException) {
            $this->assertSame(NotificationsStatusEnum::PENDING, $notification->fresh()->status);
        }
    }

    public function testItMarkFailedAfterLastAttempt(): void
    {
        $notification = $this->makeNotification();

        $this->service->markFailed($notification->id);

        $this->assertSame(NotificationsStatusEnum::ERROR, $notification->fresh()->status);
    }
}
