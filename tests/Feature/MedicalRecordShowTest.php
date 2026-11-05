<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MedicalRecords;
use App\Models\Patients;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class MedicalRecordShowTest extends TestCase
{
    use DatabaseTransactions;

    public function testItShowsMedicalRecordAsReadOnlyDocument(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        $patient = new Patients(['name' => 'Maria Teste', 'phone' => '11999990000']);
        $patient->id = 10;

        $record = new MedicalRecords(['title' => 'Sessão 11', 'date_of_consultation' => now()->toDateTimeString()]);
        $record->id = 99;
        $record->setRelation('patient', $patient);

        $record->notes_path = 'medical-records/test';

        $model = Mockery::mock(MedicalRecords::class);
        $model->shouldReceive('findByIdWithPatient')->once()->andReturn($record);
        $this->app->instance(MedicalRecords::class, $model);

        Storage::shouldReceive('temporaryUrl')->once()->andReturn('https://s3.test/notes');

        $html = $this->actingAs($user)->get(route('medical-records.show', ['id' => encrypt(99)]))->assertOk()->getContent();

        $this->assertStringContainsString('doc-page-readonly', $html);
        $this->assertStringContainsString('Maria Teste', $html);
        $this->assertStringContainsString('Sessão 11', $html);
        $this->assertStringContainsString('id="docPrint"', $html);
        $this->assertStringNotContainsString('contenteditable', $html);
        $this->assertStringNotContainsString('id="editor"', $html);
    }

    public function testItForbidsWhenRecordDoesNotBelongToUser(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        $model = Mockery::mock(MedicalRecords::class);
        $model->shouldReceive('findByIdWithPatient')->once()->andReturnNull();
        $this->app->instance(MedicalRecords::class, $model);

        $this->actingAs($user)->get(route('medical-records.show', ['id' => encrypt(99)]))->assertForbidden();
    }

    private function mockRecord(): MedicalRecords
    {
        $patient = new Patients(['name' => 'Maria Teste', 'phone' => '11999990000']);
        $patient->id = 10;

        $record = new MedicalRecords(['title' => 'Sessão 11', 'date_of_consultation' => now()->toDateTimeString()]);
        $record->id = 99;
        $record->notes_path = 'medical-records/test';
        $record->setRelation('patient', $patient);

        $model = Mockery::mock(MedicalRecords::class);
        $model->shouldReceive('findByIdWithPatient')->andReturn($record);
        $this->app->instance(MedicalRecords::class, $model);

        return $record;
    }

    public function testItShowsContentLinkWithoutCodeWhenTwoFactorIsEnabled(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional', 'two_factor' => 1, 'two_factor_secret' => 'ABCDEFGHIJKLMNOP']);
        $this->mockRecord()->updated_at = now()->subDays(10);
        Storage::shouldReceive('temporaryUrl')->once()->andReturn('https://s3.test/notes');

        $html = $this->actingAs($user)->get(route('medical-records.show', ['id' => encrypt(99)]))->assertOk()->getContent();

        $this->assertStringContainsString('s3.test', $html);
    }

    public function testItEditShowsContentLinkWithoutCodeWhenTwoFactorIsEnabled(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional', 'two_factor' => 1, 'two_factor_secret' => 'ABCDEFGHIJKLMNOP']);
        $this->mockRecord()->updated_at = now()->subDays(10);
        Storage::shouldReceive('temporaryUrl')->once()->andReturn('https://s3.test/notes');

        $html = $this->actingAs($user)->get(route('medical-records.edit', ['id' => encrypt(99)]))->assertOk()->getContent();

        $this->assertStringContainsString('s3.test', $html);
    }

    public function testItNoLongerRegistersUnlockRoute(): void
    {
        $this->assertFalse(Route::has('medical-records.unlock'));
    }
}
