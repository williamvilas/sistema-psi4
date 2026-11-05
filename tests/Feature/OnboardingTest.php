<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\HolidayHelper;
use App\Models\AgendaSettings;
use App\Models\BookingSettings;
use App\Models\PsychologistSettings;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use DatabaseTransactions;

    public function testItRedirectsPendingUserToOnboardingFromAnyPage(): void
    {
        $user = User::factory()->onboardingPending()->create();

        foreach (['dashboard', 'agenda.index', 'patients.index', 'settings.index', 'plans.index', 'help.index'] as $route) {
            $this->actingAs($user)->get(route($route))->assertRedirect(route('onboarding.index'));
        }
    }

    public function testItReturnsForbiddenJsonForPendingUserOnAjax(): void
    {
        $user = User::factory()->onboardingPending()->create();

        $this->actingAs($user)->getJson(route('agenda.paginate'))
            ->assertForbidden()
            ->assertJsonPath('redirect', route('onboarding.index'));
    }

    public function testItAllowsOnboardingAndLookupRoutesForPendingUser(): void
    {
        $user = User::factory()->onboardingPending()->create();

        $this->actingAs($user)->get(route('onboarding.index'))->assertOk();
        $this->actingAs($user)->getJson(route('onboarding.national-holidays'))->assertOk()->assertJsonStructure(['holidays' => [['date', 'name']]]);
    }

    public function testItRedirectsCompletedUserAwayFromOnboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('onboarding.index'))->assertRedirect(route('dashboard'));
        $this->actingAs($user)->postJson(route('onboarding.agenda.store'), [])->assertForbidden();
    }

    public function testItStartsAtAgendaStepAndMovesToPsychologistAfterSavingAgenda(): void
    {
        $user = User::factory()->onboardingPending()->create();

        $this->actingAs($user)->get(route('onboarding.index'))->assertSee('data-step="1"', false);

        $this->actingAs($user)->postJson(route('onboarding.agenda.store'), $this->agendaPayload())
            ->assertOk()
            ->assertJsonPath('step', 2);

        $this->assertNotNull(AgendaSettings::query()->where('user_id', $user->id)->first());
        $this->actingAs($user)->get(route('onboarding.index'))->assertSee('data-step="2"', false);
        $this->assertNull($user->refresh()->onboarding_completed_at);
    }

    public function testItRendersSuggestedHoursPerAttendanceMode(): void
    {
        $user = User::factory()->onboardingPending()->create();

        $this->actingAs($user)->get(route('onboarding.index'))
            ->assertSee('data-saved="0"', false)
            ->assertSee('value="09:00"', false)
            ->assertSee('value="23:00"', false)
            ->assertSee('&quot;in_person&quot;:[{&quot;start&quot;:&quot;09:00&quot;,&quot;end&quot;:&quot;18:00&quot;}]', false);
    }

    public function testItSavesSuggestedHoursForOnlineAndInPersonDay(): void
    {
        $user = User::factory()->onboardingPending()->create();
        $periods = [
            ['start' => '09:00', 'end' => '18:00', 'mode' => 'both'],
            ['start' => '19:00', 'end' => '23:00', 'mode' => 'online'],
        ];
        $payload = $this->agendaPayload(['attendance_mode' => 'both']);
        $payload['agenda_days']['seg'] = ['enabled' => '1', 'periods' => $periods];

        $this->actingAs($user)->postJson(route('onboarding.agenda.store'), $payload)->assertOk();

        $settings = AgendaSettings::query()->where('user_id', $user->id)->first();
        $result = $settings->periodsForDay('seg');

        $this->assertCount(2, $result);
        $this->assertSame('both', $result[0]['mode']->value);
        $this->assertSame('online', $result[1]['mode']->value);
        $this->assertSame('23:00', $settings->agenda_days['seg']['end']);
    }

    public function testItMovesToBookingStepAfterSavingPsychologist(): void
    {
        $user = User::factory()->onboardingPending()->create();

        $this->actingAs($user)->postJson(route('onboarding.agenda.store'), $this->agendaPayload())->assertOk();
        $this->actingAs($user)->postJson(route('onboarding.psychologist.store'), [
            'nome_clinica' => 'Dr(a). Fulano',
            'crp' => '06/123456',
            'document' => '529.982.247-25',
            'whatsapp' => '(12) 98884-0421',
        ])->assertOk()->assertJsonPath('step', 3);

        $this->assertNull($user->refresh()->onboarding_completed_at);
        $this->assertSame('06123456', PsychologistSettings::query()->where('user_id', $user->id)->value('crp'));
        $this->actingAs($user)->get(route('onboarding.index'))->assertSee('data-step="3"', false);
    }

    public function testItCompletesOnboardingWhenBookingIsSaved(): void
    {
        $user = User::factory()->onboardingPending()->create();

        $this->actingAs($user)->postJson(route('onboarding.agenda.store'), $this->agendaPayload())->assertOk();
        $this->actingAs($user)->postJson(route('onboarding.psychologist.store'), [
            'nome_clinica' => 'Dr(a). Fulano',
            'crp' => '06/123456',
            'document' => '529.982.247-25',
            'whatsapp' => '(12) 98884-0421',
        ])->assertOk();
        $this->actingAs($user)->postJson(route('onboarding.booking.store'), [
            'slug' => 'dr-fulano-onboarding',
            'services' => [['name' => 'Terapia individual', 'price' => '50,00']],
        ])->assertOk()->assertJsonPath('link', route('dashboard'));

        $this->assertNotNull($user->refresh()->onboarding_completed_at);
        $this->assertSame('dr-fulano-onboarding', $user->slug);
        $this->assertTrue((bool) BookingSettings::query()->where('user_id', $user->id)->value('public_booking_enabled'));
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function testItRequiresAtLeastOneServiceToSaveBooking(): void
    {
        $user = User::factory()->onboardingPending()->create();

        $this->actingAs($user)->postJson(route('onboarding.booking.store'), [
            'slug' => 'dr-fulano-onboarding',
            'services' => [],
        ])->assertStatus(422);

        $this->assertNull($user->refresh()->onboarding_completed_at);
    }

    public function testItDoesNotCompleteOnboardingWithoutAgendaAndPsychologist(): void
    {
        $user = User::factory()->onboardingPending()->create();

        $this->actingAs($user)->postJson(route('onboarding.booking.store'), [
            'slug' => 'dr-fulano-onboarding',
            'services' => [['name' => 'Terapia individual', 'price' => '50,00']],
        ])->assertStatus(400);

        $this->assertNull($user->refresh()->onboarding_completed_at);
    }

    public function testItChecksSlugAvailabilityForPendingUser(): void
    {
        $user = User::factory()->onboardingPending()->create();

        $this->actingAs($user)->getJson(route('onboarding.slug-availability', ['slug' => 'slug-livre-onboarding']))
            ->assertOk()
            ->assertJsonPath('available', true);
    }

    public function testItListsNationalHolidaysFromGivenDate(): void
    {
        $holidays = collect(HolidayHelper::national(Carbon::create(2026, 10, 7)));

        $this->assertSame('2026-10-12', $holidays->first()['date']);
        $this->assertTrue($holidays->contains('date', '2027-02-09'));
        $this->assertTrue($holidays->contains(fn ($h) => $h['date'] === '2027-03-26' && $h['name'] === 'Sexta-feira Santa'));
        $this->assertFalse($holidays->contains('date', '2026-09-07'));
    }

    public function testItRequiresAddressOnPsychologistStepWhenAttendingInPerson(): void
    {
        $user = User::factory()->onboardingPending()->create();

        $this->actingAs($user)->postJson(route('onboarding.agenda.store'), $this->agendaPayload(['attendance_mode' => 'in_person']))->assertOk();
        $this->actingAs($user)->postJson(route('onboarding.psychologist.store'), [
            'nome_clinica' => 'Dr(a). Fulano',
            'crp' => '06/123456',
            'document' => '529.982.247-25',
            'whatsapp' => '(12) 98884-0421',
        ])->assertUnprocessable()->assertJsonValidationErrors(['rua', 'numero', 'cidade']);

        $this->actingAs($user)->postJson(route('onboarding.psychologist.store'), [
            'nome_clinica' => 'Dr(a). Fulano',
            'crp' => '06/123456',
            'document' => '529.982.247-25',
            'whatsapp' => '(12) 98884-0421',
            'rua' => 'Rua das Flores',
            'numero' => '100',
            'cidade' => 'Campinas',
        ])->assertOk()->assertJsonPath('step', 3);

        $this->assertTrue(PsychologistSettings::query()->where('user_id', $user->id)->first()->hasAddress());
    }

    public function testItAllowsZipcodeLookupForPendingUser(): void
    {
        $user = User::factory()->onboardingPending()->create();

        $this->actingAs($user)->getJson(route('onboarding.zipcode.show', ['zipcode' => '123']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['zipcode']);
    }

    private function agendaPayload(array $override = []): array
    {
        $days = [];
        foreach (['seg', 'ter', 'qua', 'qui', 'sex'] as $day) {
            $days[$day] = ['enabled' => '1', 'start' => '09:00', 'end' => '23:00'];
        }

        return [
            'consultation_time' => '00:50',
            'available_time_minutes' => '00:10',
            'agenda_days' => $days,
            'holidays' => [['date' => '2026-12-25', 'name' => 'Natal']],
        ] + $override;
    }
}
