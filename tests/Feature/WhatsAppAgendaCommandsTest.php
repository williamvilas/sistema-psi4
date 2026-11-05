<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AgendaStatusEnum;
use App\Enums\NotificationRecipientEnum;
use App\Enums\NotificationTypeEnum;
use App\Helpers\CacheHelper;
use App\Models\Agenda;
use App\Models\AgendaSettings;
use App\Models\Notifications;
use App\Models\Patients;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppAgendaCommandsTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-02 15:00:00', 'UTC'));

        $this->user = User::factory()->create(['whatsapp' => '11988887777']);
        CacheHelper::forget('agenda_settings_user_'.$this->user->id);

        AgendaSettings::create([
            'user_id' => $this->user->id,
            'agenda_days' => [],
            'reminder_in_person_minutes' => 1440,
            'reminder_online_minutes' => 120,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CacheHelper::forget('agenda_settings_user_'.$this->user->id);

        parent::tearDown();
    }

    private function makeAgenda(Carbon $scheduledOn, string $type = 'online', ?string $roomLink = 'https://meet.example.com/abc'): Agenda
    {
        $patient = Patients::create(['user_id' => $this->user->id, 'name' => 'Maria Paciente', 'phone' => '119'.random_int(10000000, 99999999)]);

        return Agenda::create([
            'user_id' => $this->user->id,
            'patient_id' => $patient->id,
            'type' => $type,
            'scheduled_on' => $scheduledOn,
            'ends_at' => $scheduledOn->copy()->addHour(),
            'status' => AgendaStatusEnum::SCHEDULED->value,
            'room_link' => $roomLink,
        ]);
    }

    private function notificationsOf(Agenda $agenda, NotificationTypeEnum $type, ?NotificationRecipientEnum $recipient = null): int
    {
        return Notifications::where('agenda_id', $agenda->id)
            ->where('type', $type->value)
            ->when($recipient, fn ($q) => $q->where('recipient', $recipient->value))
            ->count();
    }

    public function testItSendReminderWhenDueAndOnlyOnce(): void
    {
        $agenda = $this->makeAgenda(now()->addHours(4));

        // Ainda faltam 2h para o lembrete (120 minutos antes)
        $this->artisan('app:send-agenda-reminders-command')->assertSuccessful();
        $this->assertSame(0, $this->notificationsOf($agenda, NotificationTypeEnum::AGENDA_REMINDER));

        Carbon::setTestNow(now()->addHours(2));
        $this->artisan('app:send-agenda-reminders-command')->assertSuccessful();
        $this->artisan('app:send-agenda-reminders-command')->assertSuccessful();

        $this->assertSame(1, $this->notificationsOf($agenda, NotificationTypeEnum::AGENDA_REMINDER, NotificationRecipientEnum::PATIENT));
        $this->assertNull($agenda->fresh()->reminder_at);
    }

    public function testItDoNotSendReminderOfCanceledAgenda(): void
    {
        $agenda = $this->makeAgenda(now()->addHours(4));
        $agenda->update(['status' => AgendaStatusEnum::CANCELED->value]);

        Carbon::setTestNow(now()->addHours(2));
        $this->artisan('app:send-agenda-reminders-command')->assertSuccessful();

        $this->assertSame(0, $this->notificationsOf($agenda, NotificationTypeEnum::AGENDA_REMINDER));
    }

    public function testItSendRoomLinkFiveMinutesBeforeToPatientAndPsychologist(): void
    {
        $soon = $this->makeAgenda(now()->addMinutes(5));
        $later = $this->makeAgenda(now()->addMinutes(30));
        $inPerson = $this->makeAgenda(now()->addMinutes(4), 'in_person', null);

        $this->artisan('app:send-room-links-command')->assertSuccessful();
        $this->artisan('app:send-room-links-command')->assertSuccessful();

        $this->assertSame(1, $this->notificationsOf($soon, NotificationTypeEnum::AGENDA_ROOM_LINK, NotificationRecipientEnum::PATIENT));
        $this->assertSame(1, $this->notificationsOf($soon, NotificationTypeEnum::AGENDA_ROOM_LINK, NotificationRecipientEnum::PSYCHOLOGIST));
        $this->assertSame(0, $this->notificationsOf($later, NotificationTypeEnum::AGENDA_ROOM_LINK));
        $this->assertSame(0, $this->notificationsOf($inPerson, NotificationTypeEnum::AGENDA_ROOM_LINK));
    }

    public function testItWarnPsychologistAndRetryWhenRoomLinkIsMissing(): void
    {
        $agenda = $this->makeAgenda(now()->addMinutes(5), roomLink: null);

        $this->artisan('app:send-room-links-command')->assertSuccessful();

        $this->assertSame(1, $this->notificationsOf($agenda, NotificationTypeEnum::AGENDA_MEET_MISSING, NotificationRecipientEnum::PSYCHOLOGIST));
        $this->assertSame(0, $this->notificationsOf($agenda, NotificationTypeEnum::AGENDA_ROOM_LINK));

        // O link do Meet chega depois: a próxima execução envia
        $agenda->updateQuietly(['room_link' => 'https://meet.example.com/xyz']);
        $this->artisan('app:send-room-links-command')->assertSuccessful();

        $this->assertSame(2, $this->notificationsOf($agenda, NotificationTypeEnum::AGENDA_ROOM_LINK));
    }
}
