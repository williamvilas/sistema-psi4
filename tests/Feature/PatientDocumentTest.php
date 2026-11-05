<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Patients;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PatientDocumentTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function storePayload(array $override = []): array
    {
        return array_merge(['name' => 'Ana Souza', 'phone' => '(11) 99999-0000', 'document' => '111.444.777-35'], $override);
    }

    public function testItRequiresDocumentOnStoreUnlessPatientHasNoDocument(): void
    {
        $this->actingAs($this->user)->postJson(route('patients.store'), $this->storePayload(['document' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('document');

        $this->actingAs($this->user)->postJson(route('patients.store'), $this->storePayload(['document' => '', 'no_document' => 1]))
            ->assertOk();

        $this->assertNull(Patients::query()->where('user_id', $this->user->id)->firstOrFail()->document);
    }

    public function testItIgnoresDocumentOnStoreWhenPatientHasNoDocument(): void
    {
        $this->actingAs($this->user)->postJson(route('patients.store'), $this->storePayload(['no_document' => 1]))->assertOk();

        $this->assertNull(Patients::query()->where('user_id', $this->user->id)->firstOrFail()->document);
    }

    public function testItRequiresDocumentOnUpdateUnlessPatientHasNoDocument(): void
    {
        $patient = Patients::query()->create(['user_id' => $this->user->id, 'name' => 'Ana Souza', 'document' => '11144477735', 'phone' => '11999990000']);
        $route = route('patients.update', ['id' => encrypt($patient->id)]);
        $payload = ['id' => encrypt($patient->id), 'name' => 'Ana Souza', 'phone' => '(11) 99999-0000'];

        $this->actingAs($this->user)->putJson($route, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('document');

        $this->actingAs($this->user)->putJson($route, $payload + ['no_document' => 1])->assertOk();

        $this->assertNull($patient->fresh()->document);
    }

    public function testItShowsNoDocumentCheckboxOnPatientForm(): void
    {
        $patient = Patients::query()->create(['user_id' => $this->user->id, 'name' => 'Ana Souza', 'phone' => '11999990000']);

        $this->actingAs($this->user)->get(route('patients.create'))
            ->assertOk()
            ->assertSee('Paciente sem CPF');

        $html = $this->actingAs($this->user)->get(route('patients.edit', ['id' => encrypt($patient->id)]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/name="no_document"[^>]*checked/', $html);
    }
}
