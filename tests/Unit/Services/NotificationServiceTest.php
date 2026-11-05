<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AgendaStatusEnum;
use App\Enums\NotificationChannelEnum;
use App\Enums\NotificationRecipientEnum;
use App\Enums\NotificationsStatusEnum;
use App\Enums\NotificationTypeEnum;
use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\Agenda;
use App\Models\Notifications;
use App\Models\Patients;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private NotificationService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Evita que o observer da agenda dispare o job durante o arrange
        Queue::fake();
        Log::spy();

        $this->service = new NotificationService(new Agenda(), new Notifications());
        $this->user = User::factory()->create(['whatsapp' => '11988887777']);
    }

    private function makeAgenda(array $patient = []): Agenda
    {
        $patient = Patients::create($patient + [
            'user_id' => $this->user->id,
            'name' => 'Maria Paciente',
            'phone' => '11999998888',
            'email' => 'maria@example.com',
        ]);

        return Agenda::create([
            'user_id' => $this->user->id,
            'patient_id' => $patient->id,
            'type' => 'online',
            'scheduled_on' => now('UTC')->addDay()->startOfHour(),
            'ends_at' => now('UTC')->addDay()->startOfHour()->addHour(),
            'status' => AgendaStatusEnum::SCHEDULED->value,
        ]);
    }

    public function testItNotifyAgendaAttendancePendingOnlyPsychologistAndOnce(): void
    {
        $agenda = $this->makeAgenda();

        $this->assertTrue($this->service->notifyAgendaAttendancePending($agenda->id));
        $this->assertFalse($this->service->notifyAgendaAttendancePending($agenda->id));

        $notifications = Notifications::where('agenda_id', $agenda->id)->get();
        $this->assertCount(1, $notifications);
        $this->assertSame(NotificationRecipientEnum::PSYCHOLOGIST, $notifications[0]->recipient);
        $this->assertSame(NotificationTypeEnum::AGENDA_ATTENDANCE_PENDING, $notifications[0]->type);
        $this->assertStringContainsString('Maria Paciente', $notifications[0]->message);
    }

    public function testItNotifyAgendaAttendancePendingReturnsFalseWhenAgendaNotFound(): void
    {
        $this->assertFalse($this->service->notifyAgendaAttendancePending(0));
    }

    public function testItNotifyOnlyPatientWhenScheduledByPsychologist(): void
    {
        $agenda = $this->makeAgenda();

        $total = $this->service->notifyAgendaStatus($agenda->id, AgendaStatusEnum::SCHEDULED);

        $this->assertSame(1, $total);
        $this->assertSame(1, Notifications::where('agenda_id', $agenda->id)->count());

        $patient = Notifications::where('agenda_id', $agenda->id)->where('recipient', NotificationRecipientEnum::PATIENT->value)->first();
        $this->assertSame(NotificationTypeEnum::AGENDA_SCHEDULED, $patient->type);
        $this->assertSame(NotificationChannelEnum::WHATSAPP, $patient->channel);
        $this->assertSame(NotificationsStatusEnum::PENDING, $patient->status);
        $this->assertSame($this->user->id, $patient->user_id);
        // Paciente é tratado pelo primeiro nome e a mensagem não cita terapia nem sessão
        $this->assertStringContainsString('Olá, Maria!', $patient->message);
        $this->assertStringNotContainsString('sessão', $patient->message);
    }

    public function testItNotifyPsychologistOfPublicBooking(): void
    {
        $agenda = $this->makeAgenda();

        $this->assertTrue($this->service->notifyPublicBooking($agenda->id));
        $this->assertFalse($this->service->notifyPublicBooking($agenda->id));

        $notification = Notifications::where('agenda_id', $agenda->id)->sole();
        $this->assertSame(NotificationRecipientEnum::PSYCHOLOGIST, $notification->recipient);
        $this->assertSame(NotificationTypeEnum::AGENDA_SCHEDULED, $notification->type);
        $this->assertStringContainsString('Maria Paciente', $notification->message);
    }

    public function testItNotifyAgendaReminderOnlyForOpenAgenda(): void
    {
        $canceled = $this->makeAgenda(['phone' => '11977776666']);
        $canceled->updateQuietly(['status' => AgendaStatusEnum::CANCELED->value, 'scheduled_on' => $canceled->scheduled_on->copy()->addHours(2)]);
        $agenda = $this->makeAgenda();

        $this->assertTrue($this->service->notifyAgendaReminder($agenda->id));
        $this->assertFalse($this->service->notifyAgendaReminder($agenda->id));
        $this->assertFalse($this->service->notifyAgendaReminder($canceled->id));

        $this->assertDatabaseHas('notifications', [
            'agenda_id' => $agenda->id,
            'recipient' => NotificationRecipientEnum::PATIENT->value,
            'type' => NotificationTypeEnum::AGENDA_REMINDER->value,
        ]);
    }

    public function testItNotifyRoomLinkToPatientAndPsychologist(): void
    {
        $agenda = $this->makeAgenda();
        $agenda->updateQuietly(['room_link' => 'https://meet.example.com/abc']);

        $this->assertSame(2, $this->service->notifyRoomLink($agenda->id));
        $this->assertSame(0, $this->service->notifyRoomLink($agenda->id));

        $patient = Notifications::where('agenda_id', $agenda->id)->where('recipient', NotificationRecipientEnum::PATIENT->value)->sole();
        $this->assertSame(NotificationTypeEnum::AGENDA_ROOM_LINK, $patient->type);
        $this->assertStringContainsString('https://meet.example.com/abc', $patient->message);
    }

    public function testItNotifyMeetMissingToPsychologistWhenRoomLinkIsEmpty(): void
    {
        $agenda = $this->makeAgenda();

        $this->assertSame(1, $this->service->notifyRoomLink($agenda->id));

        $notification = Notifications::where('agenda_id', $agenda->id)->sole();
        $this->assertSame(NotificationRecipientEnum::PSYCHOLOGIST, $notification->recipient);
        $this->assertSame(NotificationTypeEnum::AGENDA_MEET_MISSING, $notification->type);
    }

    public function testItDoNotNotifyRoomLinkForInPersonAgenda(): void
    {
        $agenda = $this->makeAgenda();
        $agenda->updateQuietly(['type' => 'in_person', 'room_link' => 'https://meet.example.com/abc']);

        $this->assertSame(0, $this->service->notifyRoomLink($agenda->id));
    }

    public function testItNotifyRescheduleRequestToPsychologist(): void
    {
        $agenda = $this->makeAgenda();

        $this->assertTrue($this->service->notifyRescheduleRequest($agenda->id));

        $this->assertDatabaseHas('notifications', [
            'agenda_id' => $agenda->id,
            'recipient' => NotificationRecipientEnum::PSYCHOLOGIST->value,
            'type' => NotificationTypeEnum::AGENDA_RESCHEDULE_REQUEST->value,
        ]);
    }

    public function testItUseEmailWhenPatientOptedOutOfWhatsApp(): void
    {
        $agenda = $this->makeAgenda(['whatsapp_notifications' => false]);

        $this->service->notifyAgendaStatus($agenda->id, AgendaStatusEnum::SCHEDULED);

        $this->assertDatabaseHas('notifications', [
            'agenda_id' => $agenda->id,
            'channel' => NotificationChannelEnum::EMAIL->value,
        ]);
    }

    public function testItNotifyFreePlanAccount(): void
    {
        $this->user->update(['current_plan' => 'free']);
        $agenda = $this->makeAgenda();

        $this->assertGreaterThan(0, $this->service->notifyAgendaStatus($agenda->id, AgendaStatusEnum::SCHEDULED));
    }

    public function testItDispatchWhatsAppSendOnlyForTypesWithTemplate(): void
    {
        config(['whatsapp.enabled' => true]);
        $agenda = $this->makeAgenda();

        $this->service->notifyAgendaStatus($agenda->id, AgendaStatusEnum::SCHEDULED);
        $this->service->notifyAgendaStatus($agenda->id, AgendaStatusEnum::NO_SHOW);

        $scheduled = Notifications::where('agenda_id', $agenda->id)->where('type', NotificationTypeEnum::AGENDA_SCHEDULED->value)->sole();
        Queue::assertPushed(SendWhatsAppNotificationJob::class, 1);
        Queue::assertPushed(SendWhatsAppNotificationJob::class, fn ($job) => $job->notificationId === $scheduled->id);
    }

    public function testItDoNotDispatchWhatsAppSendWhenDisabled(): void
    {
        config(['whatsapp.enabled' => false]);
        $agenda = $this->makeAgenda();

        $this->service->notifyAgendaStatus($agenda->id, AgendaStatusEnum::SCHEDULED);

        Queue::assertNotPushed(SendWhatsAppNotificationJob::class);
    }

    public function testItNotifyAgainAfterAgendaIsRescheduled(): void
    {
        $agenda = $this->makeAgenda();
        $this->service->notifyAgendaReminder($agenda->id);

        $agenda->update(['scheduled_on' => $agenda->scheduled_on->copy()->addDay(), 'ends_at' => $agenda->ends_at->copy()->addDay()]);

        $this->assertTrue($this->service->notifyAgendaReminder($agenda->id));
        $this->assertSame(2, Notifications::where('agenda_id', $agenda->id)->count());
    }

    public function testItNotifyOnlyPatientWhenCanceled(): void
    {
        $agenda = $this->makeAgenda();

        $total = $this->service->notifyAgendaStatus($agenda->id, AgendaStatusEnum::CANCELED);

        $this->assertSame(1, $total);
        $this->assertDatabaseHas('notifications', [
            'agenda_id' => $agenda->id,
            'recipient' => NotificationRecipientEnum::PATIENT->value,
            'type' => NotificationTypeEnum::AGENDA_CANCELED->value,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'agenda_id' => $agenda->id,
            'recipient' => NotificationRecipientEnum::PSYCHOLOGIST->value,
        ]);
    }

    public function testItNotifyOnlyPatientWhenNoShowAndCompleted(): void
    {
        $agenda = $this->makeAgenda();

        $this->assertSame(1, $this->service->notifyAgendaStatus($agenda->id, AgendaStatusEnum::NO_SHOW));
        $this->assertSame(1, $this->service->notifyAgendaStatus($agenda->id, AgendaStatusEnum::COMPLETED));
    }

    public function testItDoNotNotifyWhenStatusIsConfirmed(): void
    {
        $agenda = $this->makeAgenda();

        $total = $this->service->notifyAgendaStatus($agenda->id, AgendaStatusEnum::CONFIRMED);

        $this->assertSame(0, $total);
        $this->assertDatabaseMissing('notifications', ['agenda_id' => $agenda->id]);
    }

    public function testItIgnoreDuplicatedNotification(): void
    {
        $agenda = $this->makeAgenda();

        $this->service->notifyAgendaStatus($agenda->id, AgendaStatusEnum::SCHEDULED);
        $total = $this->service->notifyAgendaStatus($agenda->id, AgendaStatusEnum::SCHEDULED);

        $this->assertSame(0, $total);
        $this->assertSame(1, Notifications::where('agenda_id', $agenda->id)->count());
    }

    public function testItUseEmailWhenPatientHasNoPhone(): void
    {
        $agenda = $this->makeAgenda(['phone' => '']);

        $this->service->notifyAgendaStatus($agenda->id, AgendaStatusEnum::CANCELED);

        $this->assertDatabaseHas('notifications', [
            'agenda_id' => $agenda->id,
            'channel' => NotificationChannelEnum::EMAIL->value,
        ]);
    }

    public function testItSkipRecipientWithoutContact(): void
    {
        $agenda = $this->makeAgenda(['phone' => '', 'email' => null]);

        $total = $this->service->notifyAgendaStatus($agenda->id, AgendaStatusEnum::CANCELED);

        $this->assertSame(0, $total);
    }

    public function testItReturnZeroWhenAgendaNotFound(): void
    {
        $this->assertSame(0, $this->service->notifyAgendaStatus(0, AgendaStatusEnum::SCHEDULED));
    }
}
