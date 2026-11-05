<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AgendaStatusEnum;
use App\Helpers\CacheHelper;
use App\Jobs\RefreshAgendaRemindersJob;
use App\Models\Agenda;
use App\Models\AgendaSettings;
use App\Models\Patients;
use App\Models\User;
use App\Services\AgendaReminderService;
use App\Services\AgendaSettingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AgendaReminderServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AgendaReminderService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // 07:00 em São Paulo (fuso da psicóloga), fora do horário de silêncio
        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00:00', 'UTC'));

        $this->service = app(AgendaReminderService::class);
        $this->user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);

        // O id do usuário se repete entre testes (rollback): descarta configurações em cache de outro teste
        CacheHelper::forget('agenda_settings_user_'.$this->user->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CacheHelper::forget('agenda_settings_user_'.$this->user->id);

        parent::tearDown();
    }

    private function settings(?int $inPerson = 1440, ?int $online = 120): void
    {
        AgendaSettings::create([
            'user_id' => $this->user->id,
            'agenda_days' => [],
            'reminder_in_person_minutes' => $inPerson,
            'reminder_online_minutes' => $online,
        ]);
    }

    private function makeAgenda(string $scheduledOnUtc, string $type = 'in_person'): Agenda
    {
        $patient = Patients::create(['user_id' => $this->user->id, 'name' => 'Maria Paciente', 'phone' => '11999998888']);

        return Agenda::create([
            'user_id' => $this->user->id,
            'patient_id' => $patient->id,
            'type' => $type,
            'scheduled_on' => Carbon::parse($scheduledOnUtc, 'UTC'),
            'ends_at' => Carbon::parse($scheduledOnUtc, 'UTC')->addHour(),
            'status' => AgendaStatusEnum::SCHEDULED->value,
        ]);
    }

    public function testItReminderAtUsesAdvanceOfEachType(): void
    {
        $this->settings(inPerson: 1440, online: 120);
        $scheduledOn = Carbon::parse('2026-11-04 17:00:00', 'UTC');

        $inPerson = $this->service->reminderAt($this->user->id, 'in_person', $scheduledOn);
        $online = $this->service->reminderAt($this->user->id, 'online', $scheduledOn);

        $this->assertSame('2026-11-03 17:00:00', $inPerson->utc()->toDateTimeString());
        $this->assertSame('2026-11-04 15:00:00', $online->utc()->toDateTimeString());
    }

    public function testItReminderAtReturnsNullWhenReminderIsDisabled(): void
    {
        $this->settings(inPerson: null, online: 120);

        $this->assertNull($this->service->reminderAt($this->user->id, 'in_person', Carbon::parse('2026-11-04 17:00:00', 'UTC')));
    }

    public function testItReminderAtReturnsNullWithoutSettings(): void
    {
        $this->assertNull($this->service->reminderAt($this->user->id, 'online', Carbon::parse('2026-11-04 17:00:00', 'UTC')));
    }

    public function testItReminderAtReturnsNullWhenBookedInsideTheAdvance(): void
    {
        $this->settings(online: 120);

        $this->assertNull($this->service->reminderAt($this->user->id, 'online', now()->addHour()));
    }

    public function testItReminderAtSendsNowWhenLateAndAllowed(): void
    {
        $this->settings(online: 120);

        $reminderAt = $this->service->reminderAt($this->user->id, 'online', now()->addHour(), sendLate: true);

        $this->assertTrue($reminderAt->equalTo(now()));
    }

    public function testItReminderAtReturnsNullWhenAgendaStartsTooSoon(): void
    {
        $this->settings(online: 120);

        $this->assertNull($this->service->reminderAt($this->user->id, 'online', now()->addMinutes(5), sendLate: true));
    }

    public function testItReminderAtMovesReminderOutOfQuietHoursToPreviousEvening(): void
    {
        $this->settings(inPerson: 360);

        // Consulta às 07:00 em São Paulo: 6h antes seria 01:00, vai para 21:00 da noite anterior
        $reminderAt = $this->service->reminderAt($this->user->id, 'in_person', Carbon::parse('2026-11-04 10:00:00', 'UTC'));

        $this->assertSame('2026-11-04 00:00:00', $reminderAt->utc()->toDateTimeString());
    }

    public function testItReminderAtDelaysLateReminderUntilQuietHoursEnd(): void
    {
        $this->settings(inPerson: 1440);
        // 23:00 em São Paulo
        Carbon::setTestNow(Carbon::parse('2026-11-03 02:00:00', 'UTC'));

        $reminderAt = $this->service->reminderAt($this->user->id, 'in_person', Carbon::parse('2026-11-03 15:00:00', 'UTC'), sendLate: true);

        // 07:00 em São Paulo
        $this->assertSame('2026-11-03 10:00:00', $reminderAt->utc()->toDateTimeString());
    }

    public function testItComputeReminderAtWhenAgendaIsCreated(): void
    {
        $this->settings(inPerson: 1440);

        $agenda = $this->makeAgenda('2026-11-04 17:00:00');

        $this->assertSame('2026-11-03 17:00:00', $agenda->fresh()->reminder_at->toDateTimeString());
    }

    public function testItSendReminderSoonWhenAgendaIsRescheduledInsideTheAdvance(): void
    {
        $this->settings(inPerson: 1440);
        $agenda = $this->makeAgenda('2026-11-06 17:00:00');

        $agenda->update(['scheduled_on' => now()->addHours(5), 'ends_at' => now()->addHours(6)]);

        $this->assertTrue($agenda->fresh()->reminder_at->equalTo(now()));
    }

    public function testItClearReminderAtWhenAgendaIsCanceled(): void
    {
        $this->settings(inPerson: 1440);
        $agenda = $this->makeAgenda('2026-11-04 17:00:00');

        $agenda->update(['status' => AgendaStatusEnum::CANCELED->value]);

        $this->assertNull($agenda->fresh()->reminder_at);
    }

    public function testItRefreshByUserRecomputeOpenFutureAgendas(): void
    {
        $this->settings(inPerson: 1440);
        $agenda = $this->makeAgenda('2026-11-03 17:00:00');
        $this->assertSame('2026-11-02 17:00:00', $agenda->fresh()->reminder_at->toDateTimeString());

        app(AgendaSettingService::class)->store([
            'consultation_time' => '00:50',
            'available_time_minutes' => '00:10',
            'agenda_days' => [],
            'lunch_enabled' => false,
            'lunch_start' => null,
            'lunch_end' => null,
            'holidays' => [],
            'reminder_in_person_minutes' => 2880,
        ], $this->user->id);

        Queue::assertPushed(RefreshAgendaRemindersJob::class, fn ($job) => $job->userId === $this->user->id);

        $total = $this->service->refreshByUser($this->user->id);

        $this->assertSame(1, $total);
        // 48h antes já passou e o lembrete ainda não tinha saído: sai agora
        $this->assertTrue($agenda->fresh()->reminder_at->equalTo(now()));
    }

    public function testItRefreshByUserKeepsSentReminderCleared(): void
    {
        $this->settings(inPerson: 1440);
        $agenda = $this->makeAgenda('2026-11-03 17:00:00');
        $agenda->updateReminderAtQuietly($agenda->id, null);
        AgendaSettings::where('user_id', $this->user->id)->update(['reminder_in_person_minutes' => 2880]);
        CacheHelper::forget('agenda_settings_user_'.$this->user->id);

        $this->service->refreshByUser($this->user->id);

        $this->assertNull($agenda->fresh()->reminder_at);
    }
}
