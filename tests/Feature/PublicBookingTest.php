<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GoogleCalendarStatusEnum;
use App\Models\Agenda;
use App\Models\AgendaSettings;
use App\Models\BookingSettings;
use App\Models\GoogleCalendarAccount;
use App\Models\Patients;
use App\Models\PsychologistServices;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PublicBookingTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private PsychologistServices $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['slug' => 'dra-feature-'.uniqid()]);

        BookingSettings::create(['user_id' => $this->user->id, 'public_booking_enabled' => true]);

        GoogleCalendarAccount::query()->create([
            'user_id' => $this->user->id,
            'access_token' => 'token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
            'status' => GoogleCalendarStatusEnum::INTEGRATED->value,
        ]);

        AgendaSettings::create([
            'user_id' => $this->user->id,
            'agenda_days' => ['seg' => ['enabled' => '1', 'start' => '08:00', 'end' => '12:00']],
            'lunch_enabled' => false,
        ]);

        $this->service = PsychologistServices::create(['user_id' => $this->user->id, 'name' => 'Terapia de casal', 'price' => 90]);
    }

    public function testItShowPageWhenEnabled(): void
    {
        $this->get('/lp/'.$this->user->slug)
            ->assertOk()
            ->assertSee($this->user->name)
            ->assertSee('Atendimento online');
    }

    public function testItShowBookingFormWithoutGoogleConnected(): void
    {
        GoogleCalendarAccount::query()->where('user_id', $this->user->id)->delete();

        $this->get('/lp/'.$this->user->slug)
            ->assertOk()
            ->assertSee('form-public-booking');
    }

    public function testItReturnNotFoundWhenSlugDoesNotExist(): void
    {
        $this->get('/lp/nao-existe-'.uniqid())->assertNotFound();
    }

    public function testItReturnAvailabilityJson(): void
    {
        $this->getJson('/lp/'.$this->user->slug.'/availability')
            ->assertOk()
            ->assertJsonStructure(['days' => [['date', 'slots']]]);
    }

    public function testItBookAndShowConfirmationPage(): void
    {
        $monday = now($this->user->timezone)->addDays(2)->next(Carbon::MONDAY)->toDateString();

        $response = $this->postJson('/lp/'.$this->user->slug.'/book', [
            'name' => 'Maria da Silva',
            'phone' => '(11) 98765-4321',
            'document' => '111.444.777-35',
            'has_payer' => '0',
            'service_id' => $this->service->id,
            'type' => 'online',
            'date' => $monday,
            'time' => '09:00',
        ])->assertOk();

        $this->assertSame(1, Patients::where('user_id', $this->user->id)->count());
        $this->assertSame(1, Agenda::where('user_id', $this->user->id)->count());

        $this->get($response->json('link'))
            ->assertOk()
            ->assertSee('Agendamento realizado com sucesso!')
            ->assertSee('Terapia de casal');
    }

    private function booking(array $override = []): array
    {
        return array_merge([
            'name' => 'Maria da Silva',
            'phone' => '(11) 98765-4321',
            'document' => '111.444.777-35',
            'has_payer' => '0',
            'service_id' => $this->service->id,
            'type' => 'online',
            'date' => now($this->user->timezone)->addDays(2)->next(Carbon::MONDAY)->toDateString(),
            'time' => '09:00',
        ], $override);
    }

    public function testItRequiresCpfAndPayerAnswerUnlessPatientHasNoCpf(): void
    {
        $this->postJson('/lp/'.$this->user->slug.'/book', $this->booking(['document' => '', 'has_payer' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['document', 'has_payer']);

        $this->postJson('/lp/'.$this->user->slug.'/book', $this->booking(['document' => '111.111.111-11']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('document');

        $this->assertSame(0, Patients::where('user_id', $this->user->id)->count());
    }

    public function testItBooksWithoutCpfOrEmail(): void
    {
        $this->postJson('/lp/'.$this->user->slug.'/book', $this->booking([
            'document' => '',
            'has_payer' => null,
            'no_document' => '1',
        ]))->assertOk();

        $patient = Patients::where('user_id', $this->user->id)->firstOrFail();
        $this->assertNull($patient->document);
        $this->assertNull($patient->email);
    }

    public function testItIgnoresCpfSentWithNoDocument(): void
    {
        $this->postJson('/lp/'.$this->user->slug.'/book', $this->booking([
            'no_document' => '1',
            'has_payer' => '1',
            'payer_document' => '390.533.447-05',
        ]))->assertOk();

        $patient = Patients::where('user_id', $this->user->id)->firstOrFail();
        $this->assertNull($patient->document);
        $this->assertNull($patient->payer_document);
    }

    public function testItStoresPayerWhenPatientIsNotThePayer(): void
    {
        $this->postJson('/lp/'.$this->user->slug.'/book', $this->booking([
            'has_payer' => '1',
            'payer_name' => 'João da Silva',
            'payer_document' => '390.533.447-05',
        ]))->assertOk();

        $patient = Patients::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame('11144477735', trim((string) $patient->document));
        $this->assertSame('João da Silva', $patient->payer_name);
        $this->assertSame('39053344705', $patient->payer_document);
    }

    public function testItLeavesPayerEmptyWhenPatientIsThePayer(): void
    {
        $this->postJson('/lp/'.$this->user->slug.'/book', $this->booking([
            'payer_name' => 'Ignorado',
            'payer_document' => '390.533.447-05',
        ]))->assertOk();

        $patient = Patients::where('user_id', $this->user->id)->firstOrFail();
        $this->assertNull($patient->payer_name);
        $this->assertNull($patient->payer_document);
    }

    public function testItRejectsPayerMissingOrWithSameCpfAsPatient(): void
    {
        $this->postJson('/lp/'.$this->user->slug.'/book', $this->booking(['has_payer' => '1']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payer_name', 'payer_document']);

        $this->postJson('/lp/'.$this->user->slug.'/book', $this->booking([
            'has_payer' => '1',
            'payer_name' => 'João da Silva',
            'payer_document' => '11144477735',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('payer_document');
    }

    public function testItRejectHoneypotFilled(): void
    {
        $this->postJson('/lp/'.$this->user->slug.'/book', ['website' => 'spam'])->assertStatus(422);
    }
}
