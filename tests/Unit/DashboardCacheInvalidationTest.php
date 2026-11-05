<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\DashboardCache;
use App\Models\Agenda;
use App\Models\AgendaHolidays;
use App\Models\AgendaSettings;
use App\Models\Patients;
use App\Models\User;
use App\Services\AgendaService;
use App\Services\AgendaSettingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DashboardCacheInvalidationTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private Patients $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->patient = Patients::query()->create(['user_id' => $this->user->id, 'name' => 'Ana', 'phone' => '11999990000']);
    }

    private function warmCache(): void
    {
        DashboardCache::put($this->user->id, ['warm' => true]);
        $this->assertNotNull(DashboardCache::get($this->user->id));
    }

    private function createAgenda(): Agenda
    {
        return Agenda::query()->create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'type' => 'online',
            'scheduled_on' => '2026-10-05 12:00:00',
            'ends_at' => '2026-10-05 13:00:00',
            'status' => 'scheduled',
            'price' => 100,
        ]);
    }

    public function testItForgetsCacheWhenAgendaIsCreated(): void
    {
        $this->warmCache();

        $this->createAgenda();

        $this->assertNull(DashboardCache::get($this->user->id));
    }

    public function testItForgetsCacheWhenAgendaIsUpdatedDeletedAndRestored(): void
    {
        $agenda = $this->createAgenda();

        $this->warmCache();
        $agenda->update(['status' => 'completed']);
        $this->assertNull(DashboardCache::get($this->user->id));

        $this->warmCache();
        $agenda->delete();
        $this->assertNull(DashboardCache::get($this->user->id));

        $this->warmCache();
        $agenda->restore();
        $this->assertNull(DashboardCache::get($this->user->id));
    }

    public function testItForgetsCacheWhenAgendaIsCanceledByService(): void
    {
        $agenda = $this->createAgenda();
        $settingService = new AgendaSettingService(new AgendaSettings(), new AgendaHolidays());
        $service = new AgendaService(new Agenda(), $settingService, app(\App\Services\GoogleCalendarService::class));

        $this->warmCache();
        $service->cancel($agenda->id, $this->user->id);

        $this->assertNull(DashboardCache::get($this->user->id));
        $this->assertSame('canceled', $agenda->refresh()->status->value);
    }

    public function testItForgetsCacheWhenPatientChanges(): void
    {
        $this->warmCache();
        $this->patient->update(['name' => 'Ana Maria']);
        $this->assertNull(DashboardCache::get($this->user->id));

        $this->warmCache();
        $this->patient->delete();
        $this->assertNull(DashboardCache::get($this->user->id));
    }

    public function testItKeepsCacheOfOtherUsers(): void
    {
        $other = User::factory()->create();
        DashboardCache::put($other->id, ['warm' => true]);

        $this->createAgenda();

        $this->assertNotNull(DashboardCache::get($other->id));
    }
}
