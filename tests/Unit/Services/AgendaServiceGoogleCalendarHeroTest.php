<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Agenda;
use App\Models\AgendaHolidays;
use App\Models\AgendaSettings;
use App\Models\GoogleCalendarAccount;
use App\Models\Patients;
use App\Models\User;
use App\Services\AgendaService;
use App\Services\AgendaSettingService;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class AgendaServiceGoogleCalendarHeroTest extends TestCase
{
    use DatabaseTransactions;

    private $agenda;

    private $google;

    private User $user;

    private AgendaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Auth::login($this->user);

        $this->agenda = Mockery::mock(Agenda::class);
        $this->google = Mockery::mock(GoogleCalendarService::class);

        $this->service = new AgendaService(
            $this->agenda,
            new AgendaSettingService(new AgendaSettings, new AgendaHolidays),
            $this->google,
        );
    }

    public function testItShowGoogleCalendarHeroWhenNeverUsedAgendaAndNeverConnected(): void
    {
        $this->google->shouldReceive('details')->once()->with($this->user->id)->andReturnNull();
        $this->agenda->shouldReceive('existsByUser')->once()->with($this->user->id)->andReturnFalse();

        $this->assertTrue($this->service->shouldShowGoogleCalendarHero($this->user));
    }

    public function testItHideGoogleCalendarHeroWhenDismissed(): void
    {
        $this->user->forceFill(['google_calendar_hero_dismissed_at' => now()])->save();

        $this->google->shouldNotReceive('details');
        $this->agenda->shouldNotReceive('existsByUser');

        $this->assertFalse($this->service->shouldShowGoogleCalendarHero($this->user));
    }

    public function testItHideGoogleCalendarHeroWhenGoogleAccountExists(): void
    {
        // Cobre conectada, NEEDS_REAUTH e desconectada (PENDING): todas já têm registro.
        $this->google->shouldReceive('details')->once()->with($this->user->id)->andReturn(new GoogleCalendarAccount);
        $this->agenda->shouldNotReceive('existsByUser');

        $this->assertFalse($this->service->shouldShowGoogleCalendarHero($this->user));
    }

    public function testItHideGoogleCalendarHeroWhenAgendaAlreadyUsed(): void
    {
        $this->google->shouldReceive('details')->once()->with($this->user->id)->andReturnNull();
        $this->agenda->shouldReceive('existsByUser')->once()->with($this->user->id)->andReturnTrue();

        $this->assertFalse($this->service->shouldShowGoogleCalendarHero($this->user));
    }

    public function testItDismissGoogleCalendarHeroSuccessfully(): void
    {
        $this->service->dismissGoogleCalendarHero($this->user);

        $this->assertNotNull($this->user->fresh()->google_calendar_hero_dismissed_at);
    }

    public function testItDismissGoogleCalendarHeroKeepsFirstDismissDate(): void
    {
        $first = now()->subDays(3)->startOfSecond();
        $this->user->forceFill(['google_calendar_hero_dismissed_at' => $first])->save();

        $this->service->dismissGoogleCalendarHero($this->user);

        $this->assertTrue($first->equalTo($this->user->fresh()->google_calendar_hero_dismissed_at));
    }

    public function testItExistsByUserConsidersCanceledAndDeletedAgenda(): void
    {
        $model = new Agenda;

        $this->assertFalse($model->existsByUser($this->user->id));

        $patient = Patients::query()->create(['user_id' => $this->user->id, 'name' => 'Ana', 'phone' => '11999990000']);
        $agenda = Agenda::query()->create([
            'user_id' => $this->user->id,
            'patient_id' => $patient->id,
            'type' => 'online',
            'scheduled_on' => '2026-10-05 12:00:00',
            'ends_at' => '2026-10-05 13:00:00',
            'status' => 'canceled',
            'price' => 100,
        ]);
        $agenda->delete();

        $this->assertTrue($model->existsByUser($this->user->id));
    }
}
