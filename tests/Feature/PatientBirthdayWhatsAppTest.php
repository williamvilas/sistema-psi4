<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Patients;
use App\Models\User;
use App\Services\WhatsAppLinkService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PatientBirthdayWhatsAppTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->user = User::factory()->create(['timezone' => 'America/Sao_Paulo', 'current_plan' => 'professional']);
    }

    private function createPatient(string $birthDate): Patients
    {
        return Patients::create([
            'user_id' => $this->user->id,
            'name' => 'Ana Souza',
            'phone' => '11999998888',
            'birth_date' => $birthDate,
            'birthday_md' => substr($birthDate, 5),
        ]);
    }

    private function birthdayText(): string
    {
        return e(route('whatsapp.send', ['phone' => '11999998888', 'text' => app(WhatsAppLinkService::class)->birthdayMessage('Ana Souza')]));
    }

    public function testItShowBirthdayMessageOnPatientShowWhenBirthdayIsToday(): void
    {
        $patient = $this->createPatient(now('America/Sao_Paulo')->subYears(30)->format('Y-m-d'));

        $this->actingAs($this->user)->get(route('patients.show', ['id' => encrypt($patient->id)]))
            ->assertOk()
            ->assertSee($this->birthdayText(), false)
            ->assertSee('Dar parabéns')
            ->assertSee('href="'.e(route('whatsapp.send', ['phone' => '11999998888'])).'"', false);
    }

    public function testItShowPlainWhatsAppOnPatientShowWhenNotBirthday(): void
    {
        $patient = $this->createPatient(now('America/Sao_Paulo')->subYears(30)->addDays(10)->format('Y-m-d'));

        $this->actingAs($this->user)->get(route('patients.show', ['id' => encrypt($patient->id)]))
            ->assertOk()
            ->assertDontSee($this->birthdayText(), false)
            ->assertDontSee('Dar parabéns');
    }

    public function testItPaginateWithBirthdayMessageWhenBirthdayIsToday(): void
    {
        $this->createPatient(now('America/Sao_Paulo')->subYears(30)->format('Y-m-d'));

        $html = $this->actingAs($this->user)->getJson(route('patients.paginate'))->assertOk()->json('html');

        $this->assertStringContainsString($this->birthdayText(), $html);
        $this->assertStringContainsString('Enviar parabéns pelo WhatsApp', $html);
    }

    public function testItPaginateWithoutBirthdayMessageWhenNotBirthday(): void
    {
        $this->createPatient(now('America/Sao_Paulo')->subYears(30)->addDays(10)->format('Y-m-d'));

        $html = $this->actingAs($this->user)->getJson(route('patients.paginate'))->assertOk()->json('html');

        $this->assertStringNotContainsString('text=', $html);
        $this->assertStringContainsString('Conversar no WhatsApp', $html);
    }
}
