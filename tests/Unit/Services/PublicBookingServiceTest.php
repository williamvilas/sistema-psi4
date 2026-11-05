<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\FeatureUsageEnum;
use App\Enums\GoogleCalendarStatusEnum;
use App\Enums\NotificationRecipientEnum;
use App\Enums\NotificationTypeEnum;
use App\Enums\VideoRoomRoleEnum;
use App\Exceptions\PlanLimitException;
use App\Helpers\CacheHelper;
use App\Models\Agenda;
use App\Models\AgendaSettings;
use App\Models\BookingSettings;
use App\Models\GoogleCalendarAccount;
use App\Models\Patients;
use App\Models\PsychologistServices;
use App\Models\PsychologistSettings;
use App\Models\User;
use App\Models\UserFeatureUsages;
use App\Models\VideoRoom;
use App\Services\PublicBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PublicBookingServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PublicBookingService $service;

    private User $user;

    private PsychologistServices $psychologistService;

    private string $monday;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();

        $this->service = app(PublicBookingService::class);
        $this->user = User::factory()->create(['slug' => 'dra-teste-'.uniqid(), 'current_plan' => 'professional']);

        BookingSettings::create(['user_id' => $this->user->id, 'public_booking_enabled' => true]);

        $this->connectGoogle();

        AgendaSettings::create([
            'user_id' => $this->user->id,
            'consultation_time' => '01:00:00',
            'available_time_minutes' => '00:00:00',
            'agenda_days' => ['seg' => ['enabled' => '1', 'start' => '08:00', 'end' => '12:00']],
            'lunch_enabled' => true,
            'lunch_start' => '10:00',
            'lunch_end' => '11:00',
        ]);

        $this->psychologistService = PsychologistServices::create([
            'user_id' => $this->user->id,
            'name' => 'Terapia individual',
            'price' => 50,
        ]);

        $this->monday = now($this->user->timezone)->addDays(2)->next(Carbon::MONDAY)->toDateString();
    }

    private function connectGoogle(): void
    {
        GoogleCalendarAccount::query()->create([
            'user_id' => $this->user->id,
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
            'status' => GoogleCalendarStatusEnum::INTEGRATED->value,
        ]);
    }

    public function testItFindActiveKeepsPageOnFreePlan(): void
    {
        $this->user->update(['current_plan' => 'free']);

        $this->assertNotNull($this->service->findActive($this->user->slug));
    }

    public function testItAvailabilityListsSlotsWithoutGoogle(): void
    {
        GoogleCalendarAccount::query()->where('user_id', $this->user->id)->delete();
        $context = $this->service->findActive($this->user->slug);

        $days = $this->service->availability($context);

        $this->assertNotEmpty($days);
    }

    public function testItBookWithoutGoogleCreatesAgendaWithPsi4RoomLink(): void
    {
        GoogleCalendarAccount::query()->where('user_id', $this->user->id)->delete();

        $summary = $this->service->book($this->user->slug, $this->payload());

        $this->assertMatchesRegularExpression('~^'.preg_quote(config('psi_video.jitsi_base_url'), '~').'/[a-z0-9]{12}$~', (string) $summary['room_link']);
        $this->assertSame($summary['room_link'], Agenda::query()->where('user_id', $this->user->id)->value('room_link'));
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Maria da Silva',
            'phone' => '(11) 98765-4321',
            'document' => '11144477735',
            'has_payer' => '0',
            'service_id' => $this->psychologistService->id,
            'type' => 'online',
            'date' => $this->monday,
            'time' => '08:00',
        ], $override);
    }

    private function attendInPerson(string $mode, array $periods = [['start' => '08:00', 'end' => '12:00']]): void
    {
        AgendaSettings::where('user_id', $this->user->id)->update([
            'attendance_mode' => $mode,
            'agenda_days' => json_encode(['seg' => ['enabled' => '1', 'periods' => $periods]]),
        ]);
        PsychologistSettings::query()->updateOrCreate(['user_id' => $this->user->id], [
            'rua' => 'Rua das Flores',
            'numero' => '100',
            'bairro' => 'Centro',
            'cidade' => 'Campinas',
        ]);
        CacheHelper::forget("agenda_settings_user_{$this->user->id}");
        CacheHelper::forget("psychologist_settings_user_{$this->user->id}");
    }

    public function testItAvailabilityReturnsOnlyOnlineTypeByDefault(): void
    {
        $context = $this->service->findActive($this->user->slug);

        $day = collect($this->service->availability($context))->firstWhere('date', $this->monday);

        $this->assertSame(['online'], $day['types']);
    }

    public function testItAvailabilityReturnsOnlyPeriodTypeWhenDayIsRestricted(): void
    {
        $this->attendInPerson('both', [['start' => '08:00', 'end' => '12:00', 'mode' => 'in_person']]);
        $context = $this->service->findActive($this->user->slug);

        $day = collect($this->service->availability($context))->firstWhere('date', $this->monday);

        $this->assertSame(['in_person'], $day['types']);
        $this->assertSame(['in_person'], array_keys($day['slots']));
    }

    public function testItAvailabilityGroupsSlotsByPeriodType(): void
    {
        $this->attendInPerson('both', [
            ['start' => '08:00', 'end' => '10:00', 'mode' => 'in_person'],
            ['start' => '14:00', 'end' => '16:00', 'mode' => 'online'],
        ]);
        $context = $this->service->findActive($this->user->slug);

        $day = collect($this->service->availability($context))->firstWhere('date', $this->monday);

        $this->assertSame(['online', 'in_person'], $day['types']);
        $this->assertNotContains('08:00', $day['slots']['online']);
        $this->assertContains('08:00', $day['slots']['in_person']);
        $this->assertContains('14:00', $day['slots']['online']);
        $this->assertNotContains('14:00', $day['slots']['in_person']);
    }

    public function testItProfileScheduleShowsEachPeriodWithItsMode(): void
    {
        $this->attendInPerson('both', [
            ['start' => '08:00', 'end' => '12:00', 'mode' => 'in_person'],
            ['start' => '14:00', 'end' => '19:00', 'mode' => 'online'],
        ]);

        $profile = $this->service->profile($this->service->findActive($this->user->slug));

        $this->assertSame(['Seg · 8h às 12h (presencial), 14h às 19h (online)'], $profile['schedule']);
    }

    public function testItProfileReturnsModeAndAddressForInPerson(): void
    {
        $this->attendInPerson('both');

        $profile = $this->service->profile($this->service->findActive($this->user->slug));

        $this->assertSame('both', $profile['mode']);
        $this->assertTrue($profile['in_person']);
        $this->assertSame(['Rua das Flores, 100', 'Centro, Campinas'], $profile['address']);
        $this->assertSame('https://www.google.com/maps/dir/?api=1&destination='.urlencode('Rua das Flores, 100, Centro, Campinas, Brasil'), $profile['maps_url']);
    }

    public function testItProfileReturnsNullMapsUrlWhenAddressIsIncomplete(): void
    {
        $this->attendInPerson('in_person');
        PsychologistSettings::query()->where('user_id', $this->user->id)->update(['numero' => null]);
        CacheHelper::forget("psychologist_settings_user_{$this->user->id}");

        $profile = $this->service->profile($this->service->findActive($this->user->slug));

        $this->assertNull($profile['maps_url']);
    }

    public function testItBookInPersonCreatesAgendaWithoutRoomAndReturnsAddress(): void
    {
        GoogleCalendarAccount::query()->where('user_id', $this->user->id)->delete();
        $this->attendInPerson('in_person');

        $summary = $this->service->book($this->user->slug, $this->payload(['type' => 'in_person']));

        $agenda = Agenda::query()->where('user_id', $this->user->id)->first();
        $this->assertSame('in_person', $agenda->type);
        $this->assertNull($agenda->room_link);
        $this->assertNull($summary['room_link']);
        $this->assertSame('Rua das Flores, 100 · Centro, Campinas', $summary['address']);
        $this->assertStringStartsWith('https://www.google.com/maps/dir/?api=1&destination=', $summary['maps_url']);
    }

    public function testItThrowExceptionWhenTypeNotAllowedInPeriod(): void
    {
        $this->attendInPerson('both', [
            ['start' => '08:00', 'end' => '12:00', 'mode' => 'online'],
            ['start' => '14:00', 'end' => '18:00', 'mode' => 'in_person'],
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('public-booking.mode_not_allowed'));

        $this->service->book($this->user->slug, $this->payload(['type' => 'in_person']));
    }

    public function testItFindActiveReturnsNullWhenSlugNotFound(): void
    {
        $this->assertNull($this->service->findActive('slug-que-nao-existe'));
    }

    public function testItFindActiveReturnsNullWhenBookingDisabled(): void
    {
        BookingSettings::where('user_id', $this->user->id)->update(['public_booking_enabled' => false]);
        \App\Helpers\CacheHelper::forget("booking_settings_user_{$this->user->id}");

        $this->assertNull($this->service->findActive($this->user->slug));
    }

    public function testItAvailabilityExcludesLunchAndBusySlots(): void
    {
        $patient = Patients::create(['user_id' => $this->user->id, 'name' => 'Outro', 'phone' => '11911112222']);
        $busy = Carbon::parse("{$this->monday} 08:00", $this->user->timezone)->utc();

        Agenda::create([
            'user_id' => $this->user->id,
            'patient_id' => $patient->id,
            'scheduled_on' => $busy,
            'type' => 'online',
            'ends_at' => $busy->copy()->addHour(),
            'status' => 'scheduled',
        ]);

        $context = $this->service->findActive($this->user->slug);
        $days = collect($this->service->availability($context))->firstWhere('date', $this->monday);

        $this->assertSame(['online' => ['09:00', '11:00']], $days['slots']);
    }

    public function testItBookCreatesPatientAndAgenda(): void
    {
        $summary = $this->service->book($this->user->slug, $this->payload());

        $patient = Patients::where('user_id', $this->user->id)->where('phone', '11987654321')->first();
        $this->assertNotNull($patient);
        $this->assertSame('Maria da Silva', $patient->name);

        $agenda = Agenda::where('user_id', $this->user->id)->where('patient_id', $patient->id)->first();
        $this->assertNotNull($agenda);
        $this->assertSame($this->psychologistService->id, $agenda->service_id);
        $this->assertSame('50.00', (string) $agenda->price);
        $this->assertSame('Terapia individual', $summary['service']);
    }

    public function testItBookNotifiesPsychologistOfPublicBooking(): void
    {
        $this->user->update(['whatsapp' => '11988887777']);

        $this->service->book($this->user->slug, $this->payload());

        $agenda = Agenda::where('user_id', $this->user->id)->firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'agenda_id' => $agenda->id,
            'recipient' => NotificationRecipientEnum::PSYCHOLOGIST->value,
            'type' => NotificationTypeEnum::AGENDA_SCHEDULED->value,
        ]);
    }

    public function testItBookSavesPublicBookingFeatureUsage(): void
    {
        $this->service->book($this->user->slug, $this->payload());

        $this->assertSame(1, $this->usageOf(FeatureUsageEnum::PUBLIC_BOOKING));
        $this->assertSame(0, $this->usageOf(FeatureUsageEnum::AGENDA));
    }

    public function testItBookNotSavePublicBookingFeatureUsageWhenSlotUnavailable(): void
    {
        try {
            $this->service->book($this->user->slug, $this->payload(['time' => '10:00']));
        } catch (\DomainException) {
        }

        $this->assertSame(0, $this->usageOf(FeatureUsageEnum::PUBLIC_BOOKING));
    }

    public function testItAvailabilityIsEmptyWhenAgendaLimitReached(): void
    {
        config(['plans.plans.free.total_agenda' => 3]);
        GoogleCalendarAccount::query()->where('user_id', $this->user->id)->delete();
        $this->user->update(['current_plan' => 'free']);
        $this->setUsage(FeatureUsageEnum::AGENDA, 3);

        $context = $this->service->findActive($this->user->slug);

        $this->assertNotNull($context);
        $this->assertSame([], $this->service->availability($context));
    }

    public function testItBookSharesAgendaLimitWithPsychologist(): void
    {
        config(['plans.plans.free.total_agenda' => 3]);
        $this->user->update(['current_plan' => 'free']);
        $this->setUsage(FeatureUsageEnum::AGENDA, 2);

        $this->service->book($this->user->slug, $this->payload());

        $this->assertSame(1, $this->usageOf(FeatureUsageEnum::PUBLIC_BOOKING));
    }

    private function setUsage(FeatureUsageEnum $feature, int $count): void
    {
        UserFeatureUsages::query()->create([
            'user_id' => $this->user->id,
            'feature' => $feature->value,
            'year' => now()->year,
            'month' => now()->month,
            'usage_count' => $count,
        ]);
    }

    private function usageOf(FeatureUsageEnum $feature): int
    {
        return (int) UserFeatureUsages::query()
            ->where('user_id', $this->user->id)
            ->where('feature', $feature->value)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->value('usage_count');
    }

    public function testItBookReusesPatientWithSameNumberAndSimilarName(): void
    {
        $patient = Patients::create(['user_id' => $this->user->id, 'name' => 'Maria da Silva', 'phone' => '11987654321']);

        $this->service->book($this->user->slug, $this->payload(['name' => 'maria silva']));

        $this->assertSame(1, Patients::where('user_id', $this->user->id)->count());
        $this->assertSame(1, Agenda::where('patient_id', $patient->id)->count());
    }

    public function testItBookCreatesNewPatientWhenSameNumberAndDifferentName(): void
    {
        Patients::create(['user_id' => $this->user->id, 'name' => 'Joao Pedro', 'phone' => '11987654321']);

        $this->service->book($this->user->slug, $this->payload(['name' => 'Ana Clara']));

        $this->assertSame(2, Patients::where('user_id', $this->user->id)->where('phone', '11987654321')->count());
    }

    public function testItBookStoresDocumentAndPayerOnNewPatient(): void
    {
        $this->service->book($this->user->slug, $this->payload([
            'payer_name' => 'João da Silva',
            'payer_document' => '39053344705',
        ]));

        $patient = Patients::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame('11144477735', trim((string) $patient->document));
        $this->assertSame('João da Silva', $patient->payer_name);
        $this->assertSame('39053344705', $patient->payer_document);
        $this->assertNull($patient->email);
    }

    public function testItBookWithoutCpfCreatesPatientWithPhoneInRoom(): void
    {
        $summary = $this->service->book($this->user->slug, $this->payload(['document' => null, 'type' => 'online']));

        $patient = Patients::where('user_id', $this->user->id)->firstOrFail();
        $this->assertNull($patient->document);

        $room = substr((string) $summary['room_link'], strrpos((string) $summary['room_link'], '/') + 1);
        $guest = VideoRoom::query()->where('room', $room)->firstOrFail()->participants->firstWhere('role', VideoRoomRoleEnum::GUEST);
        $this->assertNull($guest->cpf_hmac);
        $this->assertNotNull($guest->phone_hmac);
    }

    public function testItBookUsesPatientFoundByCpfWithoutChangingData(): void
    {
        $patient = Patients::create(['user_id' => $this->user->id, 'name' => 'Maria da Silva', 'document' => '11144477735', 'phone' => '11911112222']);

        $this->service->book($this->user->slug, $this->payload(['name' => 'Outro Nome', 'payer_document' => '39053344705']));

        $this->assertSame(1, Patients::where('user_id', $this->user->id)->count());
        $this->assertSame(1, Agenda::where('patient_id', $patient->id)->count());

        $patient->refresh();
        $this->assertSame('Maria da Silva', $patient->name);
        $this->assertSame('11911112222', $patient->phone);
        $this->assertNull($patient->payer_document);
    }

    public function testItBookKeepsExistingDocumentAndPayerWhenFoundByPhoneAndName(): void
    {
        $patient = Patients::create([
            'user_id' => $this->user->id,
            'name' => 'Maria da Silva',
            'document' => '39053344705',
            'phone' => '11987654321',
            'payer_document' => '52998224725',
        ]);

        $this->service->book($this->user->slug, $this->payload(['document' => '11144477735', 'payer_document' => '11144477735']));

        $patient->refresh();
        $this->assertSame('39053344705', trim((string) $patient->document));
        $this->assertSame('52998224725', $patient->payer_document);
    }

    public function testItBookDoesNotFillDocumentOfExistingPatientWithoutCpf(): void
    {
        $patient = Patients::create(['user_id' => $this->user->id, 'name' => 'Maria da Silva', 'phone' => '11987654321', 'email' => 'antigo@example.com']);

        $this->service->book($this->user->slug, $this->payload());

        $patient->refresh();
        $this->assertNull($patient->document);
        $this->assertSame('antigo@example.com', $patient->email);
    }

    public function testItThrowExceptionWhenSlotAlreadyBooked(): void
    {
        $this->service->book($this->user->slug, $this->payload());

        $this->expectException(\DomainException::class);

        $this->service->book($this->user->slug, $this->payload(['name' => 'Outra Pessoa', 'phone' => '(11) 91111-2222']));
    }

    public function testItThrowExceptionWhenSlotIsOnLunch(): void
    {
        $this->expectException(\DomainException::class);

        $this->service->book($this->user->slug, $this->payload(['time' => '10:00']));
    }

    public function testItThrowExceptionWhenDayIsNotWorkingDay(): void
    {
        $this->expectException(\DomainException::class);

        $this->service->book($this->user->slug, $this->payload(['date' => Carbon::parse($this->monday)->addDay()->toDateString()]));
    }

    public function testItThrowExceptionWhenServiceBelongsToAnotherUser(): void
    {
        $other = User::factory()->create();
        $foreign = PsychologistServices::create(['user_id' => $other->id, 'name' => 'Outro', 'price' => 10]);

        $this->expectException(\DomainException::class);

        $this->service->book($this->user->slug, $this->payload(['service_id' => $foreign->id]));
    }

    public function testItThrowExceptionWhenBookingOverAgendaLimit(): void
    {
        config(['plans.plans.free.total_agenda' => 3]);
        $this->user->update(['current_plan' => 'free']);
        $this->setUsage(FeatureUsageEnum::PUBLIC_BOOKING, 3);

        try {
            $this->service->book($this->user->slug, $this->payload());
            $this->fail('Deveria lançar DomainException');
        } catch (\DomainException $e) {
            // O paciente nunca vê a mensagem de plano/upgrade
            $this->assertSame(__('public-booking.online_unavailable'), $e->getMessage());
            $this->assertNotInstanceOf(PlanLimitException::class, $e);
            $this->assertSame(0, Patients::where('user_id', $this->user->id)->count());
            $this->assertSame(0, Agenda::where('user_id', $this->user->id)->count());
            $this->assertSame(3, $this->usageOf(FeatureUsageEnum::PUBLIC_BOOKING));
        }
    }
}
