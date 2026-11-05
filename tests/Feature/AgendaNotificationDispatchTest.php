<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AgendaStatusEnum;
use App\Jobs\SendAgendaNotificationJob;
use App\Models\Agenda;
use App\Models\Patients;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AgendaNotificationDispatchTest extends TestCase
{
    use DatabaseTransactions;

    private Agenda $agenda;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $user = User::factory()->create();
        $patient = Patients::create(['user_id' => $user->id, 'name' => 'Paciente', 'phone' => '11999998888']);

        $this->agenda = Agenda::create([
            'user_id' => $user->id,
            'patient_id' => $patient->id,
            'type' => 'online',
            'scheduled_on' => now('UTC')->addDay()->startOfHour(),
            'ends_at' => now('UTC')->addDay()->startOfHour()->addHour(),
            'status' => AgendaStatusEnum::SCHEDULED->value,
        ]);
    }

    public function testItDispatchJobWhenAgendaIsCreated(): void
    {
        Queue::assertPushed(SendAgendaNotificationJob::class, fn ($job) => $job->agendaId === $this->agenda->id
            && $job->status === AgendaStatusEnum::SCHEDULED);
    }

    public function testItDispatchJobForEachNotifiableStatusChange(): void
    {
        foreach ([AgendaStatusEnum::NO_SHOW, AgendaStatusEnum::COMPLETED, AgendaStatusEnum::CANCELED] as $status) {
            $this->agenda->update(['status' => $status->value]);

            Queue::assertPushed(SendAgendaNotificationJob::class, fn ($job) => $job->status === $status);
        }
    }

    public function testItDoNotDispatchJobWhenStatusIsConfirmed(): void
    {
        $this->agenda->update(['status' => AgendaStatusEnum::CONFIRMED->value]);

        Queue::assertPushedTimes(SendAgendaNotificationJob::class, 1);
    }

    public function testItDoNotDispatchJobWhenStatusDoesNotChange(): void
    {
        $this->agenda->update(['notes' => 'Observação']);

        Queue::assertPushedTimes(SendAgendaNotificationJob::class, 1);
    }

    public function testItDispatchJobWhenAgendaIsCanceledByModel(): void
    {
        (new Agenda())->cancel($this->agenda->user_id, $this->agenda->id);

        Queue::assertPushed(SendAgendaNotificationJob::class, fn ($job) => $job->status === AgendaStatusEnum::CANCELED);
    }
}
