<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AgendaStatusEnum;
use App\Enums\GoogleCalendarStatusEnum;
use App\Models\Agenda;
use App\Models\BookingSettings;
use App\Models\GoogleCalendarAccount;
use App\Models\Patients;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AgendaIndexTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private Patients $patient;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->user = User::factory()->create(['timezone' => 'America/Sao_Paulo', 'current_plan' => 'professional']);
        $this->patient = Patients::create([
            'user_id' => $this->user->id,
            'name' => "Ana D'Ávila",
            'phone' => '11999998888',
        ]);
    }

    private function createAgenda(string $start, AgendaStatusEnum $status = AgendaStatusEnum::SCHEDULED, ?string $roomLink = null): Agenda
    {
        $scheduledOn = now('UTC')->parse($start, 'UTC');

        return Agenda::create([
            'user_id' => $this->user->id,
            'patient_id' => $this->patient->id,
            'type' => 'online',
            'scheduled_on' => $scheduledOn,
            'ends_at' => $scheduledOn->copy()->addMinutes(50),
            'room_link' => $roomLink,
            'status' => $status->value,
        ]);
    }

    public function testItShowsHeaderActionsAndStatusChips(): void
    {
        $this->createAgenda('+2 days');

        $this->actingAs($this->user)->get(route('agenda.index'))
            ->assertOk()
            ->assertSee('data-status="'.AgendaStatusEnum::SCHEDULED->value.'"', false)
            ->assertSee('data-pending-attendance="1"', false)
            ->assertDontSee('Pendentes de finalização')
            ->assertSee('class="list-paginate agenda-listing"', false);
    }

    public function testItGroupsSessionsByDayWithRoomActions(): void
    {
        $this->createAgenda('+1 day', AgendaStatusEnum::CONFIRMED, 'https://meet.google.com/abc-defg-hij');

        $html = $this->actingAs($this->user)
            ->getJson(route('agenda.paginate', ['status' => AgendaStatusEnum::CONFIRMED->value]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('agenda-day', $html);
        $this->assertStringContainsString('Amanhã', $html);
        $this->assertStringContainsString('dash-chip-info', $html);
        $this->assertStringContainsString('Entrar na sala', $html);
        $this->assertStringContainsString('Cancelar agendamento', $html);
    }

    public function testItShowsAttendanceActionsForPendingSession(): void
    {
        $this->createAgenda('-3 hours');

        $html = $this->actingAs($this->user)
            ->getJson(route('agenda.paginate', ['pending_attendance' => 1]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('Registre a presença', $html);
        $this->assertStringContainsString('Compareceu', $html);
        $this->assertStringContainsString('Faltou', $html);
        $this->assertStringNotContainsString('Cancelar agendamento', $html);
    }

    public function testItListsOnlyPendingAttendanceIgnoringStatus(): void
    {
        $this->createAgenda('-3 hours', AgendaStatusEnum::CONFIRMED);
        $this->createAgenda('-1 day', AgendaStatusEnum::COMPLETED);
        $this->createAgenda('+1 day');

        $paginate = $this->actingAs($this->user)
            ->getJson(route('agenda.paginate', ['pending_attendance' => 1, 'status' => AgendaStatusEnum::COMPLETED->value]))
            ->assertOk()
            ->json('html');

        $this->assertSame(1, substr_count($paginate, 'Registre a presença'));
        $this->assertStringNotContainsString('dash-chip-success', $paginate);
    }

    public function testItEscapesPatientNameInsideJavascriptHandlers(): void
    {
        $this->createAgenda('+2 days');

        $html = $this->actingAs($this->user)
            ->getJson(route('agenda.paginate', ['status' => AgendaStatusEnum::SCHEDULED->value]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('cancelAgenda(', $html);
        $this->assertStringNotContainsString("D'Ávila", $html);
        $this->assertStringNotContainsString("D&#039;Ávila')", $html);
    }

    public function testItShowsEmptyStateWithFilterHint(): void
    {
        $html = $this->actingAs($this->user)
            ->getJson(route('agenda.paginate', ['status' => AgendaStatusEnum::CANCELED->value]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('Nenhuma sessão encontrada para este filtro.', $html);
    }

    public function testItShowsEmptyStateWithFilterHintWhenNoPendingAttendance(): void
    {
        $this->createAgenda('+1 day');

        $html = $this->actingAs($this->user)
            ->getJson(route('agenda.paginate', ['pending_attendance' => 1]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('Nenhuma sessão encontrada para este filtro.', $html);
    }

    public function testItShowsEmptyStateWhenNoFilter(): void
    {
        $html = $this->actingAs($this->user)
            ->getJson(route('agenda.paginate', ['status' => AgendaStatusEnum::SCHEDULED->value]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('Nenhuma sessão agendada.', $html);
    }

    public function testItShowsGoogleCalendarHeroWhenNeverUsedAgendaAndNeverConnected(): void
    {
        $this->actingAs($this->user)->get(route('agenda.index'))
            ->assertOk()
            ->assertSee('id="google-calendar-hero"', false)
            ->assertSee(route('settings.google-calendar.connect', ['from' => 'agenda']), false)
            ->assertSee('Agora não')
            ->assertSee('list-paginate agenda-listing d-none', false)
            ->assertSee('dash-filter-chips mb-3 agenda-listing d-none', false)
            ->assertDontSee('<div class="dash-skeleton">', false);
    }

    public function testItHidesGoogleCalendarHeroWhenAgendaWasUsed(): void
    {
        $this->createAgenda('+2 days', AgendaStatusEnum::CANCELED)->delete();

        $this->actingAs($this->user)->get(route('agenda.index'))
            ->assertOk()
            ->assertDontSee('id="google-calendar-hero"', false)
            ->assertDontSee('agenda-listing d-none', false);
    }

    public function testItHidesGoogleCalendarHeroWhenGoogleAccountExists(): void
    {
        GoogleCalendarAccount::query()->create([
            'user_id' => $this->user->id,
            'status' => GoogleCalendarStatusEnum::NEEDS_REAUTH->value,
        ]);

        $this->actingAs($this->user)->get(route('agenda.index'))
            ->assertOk()
            ->assertDontSee('id="google-calendar-hero"', false);
    }

    public function testItDismissGoogleCalendarHeroForGood(): void
    {
        $this->actingAs($this->user)
            ->patchJson(route('agenda.google-calendar-hero.dismiss'))
            ->assertOk()
            ->assertJson(['message' => __('agenda.google_hero_dismissed')]);

        $this->assertNotNull($this->user->fresh()->google_calendar_hero_dismissed_at);

        $this->actingAs($this->user->fresh())->get(route('agenda.index'))
            ->assertOk()
            ->assertDontSee('id="google-calendar-hero"', false);
    }

    public function testItShowsCopyBookingLinkWhenPublicBookingIsEnabled(): void
    {
        $this->user->update(['slug' => 'dra-agenda-'.uniqid()]);
        BookingSettings::create(['user_id' => $this->user->id, 'public_booking_enabled' => true]);
        $url = route('public-booking.show', ['slug' => $this->user->slug]);

        $this->actingAs($this->user)->get(route('agenda.index'))
            ->assertOk()
            ->assertSee('data-url="'.$url.'"', false)
            ->assertSee('copyBookingLink(this)', false)
            ->assertSee('Copiar link')
            ->assertDontSee('Personalize o endereço do seu link')
            ->assertDontSee('Agendamento online desativado');
    }

    public function testItSuggestsCustomSlugWhenBookingLinkStillUsesUuid(): void
    {
        BookingSettings::create(['user_id' => $this->user->id, 'public_booking_enabled' => true]);

        $this->actingAs($this->user)->get(route('agenda.index'))
            ->assertOk()
            ->assertSee('Copiar link')
            ->assertSee('Personalize o endereço do seu link');
    }

    public function testItInvitesToEnableWhenPublicBookingIsDisabled(): void
    {
        BookingSettings::create(['user_id' => $this->user->id, 'public_booking_enabled' => false]);

        $this->actingAs($this->user)->get(route('agenda.index'))
            ->assertOk()
            ->assertSee('Agendamento online desativado')
            ->assertSee(route('settings.index').'#agendamento-online', false)
            ->assertDontSee('copyBookingLink(this)', false)
            ->assertDontSee(route('public-booking.show', ['slug' => $this->user->slug]), false);
    }

    public function testItInvitesToEnableWhenBookingWasNeverConfigured(): void
    {
        $this->actingAs($this->user)->get(route('agenda.index'))
            ->assertOk()
            ->assertSee('Agendamento online desativado')
            ->assertDontSee('copyBookingLink(this)', false);
    }
}
