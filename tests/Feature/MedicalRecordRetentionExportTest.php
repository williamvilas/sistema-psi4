<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AccessLogs;
use App\Models\MedicalRecords;
use App\Models\Patients;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MedicalRecordRetentionExportTest extends TestCase
{
    use DatabaseTransactions;

    private function patientFor(User $user, string $name = 'Maria Teste'): Patients
    {
        return Patients::query()->create(['user_id' => $user->id, 'name' => $name, 'phone' => '11999990000']);
    }

    private function recordFor(User $user, Patients $patient, string $date, string $title = 'Sessão'): MedicalRecords
    {
        return MedicalRecords::query()->create([
            'user_id' => $user->id,
            'patient_id' => $patient->id,
            'title' => $title,
            'date_of_consultation' => $date,
            'notes_path' => 'medical-records/'.uniqid().'.bin',
        ]);
    }

    public function testItRequiresRetentionConfirmationToDeleteRecentRecord(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);
        $record = $this->recordFor($user, $this->patientFor($user), now()->subYear()->toDateString());

        $this->actingAs($user)->deleteJson(route('medical-records.delete'), ['ids' => [encrypt($record->id)]])
            ->assertStatus(400)
            ->assertJson(['message' => __('medical-records.retention_confirmation_required')]);

        $this->assertNotSoftDeleted($record);
    }

    public function testItDeletesRecentRecordWhenRetentionIsConfirmed(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);
        $record = $this->recordFor($user, $this->patientFor($user), now()->subYear()->toDateString());

        $this->actingAs($user)->deleteJson(route('medical-records.delete'), ['ids' => [encrypt($record->id)], 'retention_confirmed' => 1])
            ->assertOk();

        $this->assertSoftDeleted($record);
        $this->assertTrue(AccessLogs::query()->where('medical_record_id', $record->id)->where('action', 'medical_record_deleted')->exists());
    }

    public function testItDeletesRecordOlderThanRetentionWithoutConfirmation(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);
        $record = $this->recordFor($user, $this->patientFor($user), now()->subYears(6)->toDateString());

        $this->actingAs($user)->deleteJson(route('medical-records.delete'), ['ids' => [encrypt($record->id)]])
            ->assertOk();

        $this->assertSoftDeleted($record);
    }

    public function testItExportsPatientRecordsInChronologicalOrderAndRegistersAccess(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);
        $patient = $this->patientFor($user);
        $second = $this->recordFor($user, $patient, '2026-05-10', 'Sessão 2');
        $first = $this->recordFor($user, $patient, '2026-04-10', 'Sessão 1');

        Storage::shouldReceive('temporaryUrl')->twice()->andReturn('https://s3.test/a', 'https://s3.test/b');

        $response = $this->actingAs($user)
            ->postJson(route('medical-records.export'), ['patient_id' => encrypt($patient->id)])
            ->assertOk();

        $response->assertJsonPath('patient', 'Maria Teste');
        $response->assertJsonPath('records.0.title', 'Sessão 1');
        $response->assertJsonPath('records.0.date', '10/04/2026');
        $response->assertJsonPath('records.0.notes_path', $first->notes_path);
        $response->assertJsonPath('records.1.title', 'Sessão 2');

        $exported = AccessLogs::query()->where('user_id', $user->id)->where('action', 'medical_record_exported')->pluck('medical_record_id')->sort()->values()->all();
        $this->assertSame(collect([$first->id, $second->id])->sort()->values()->all(), $exported);
    }

    public function testItDoesNotExportAnotherUsersPatient(): void
    {
        $owner = User::factory()->create(['current_plan' => 'professional']);
        $intruder = User::factory()->create(['current_plan' => 'professional']);
        $patient = $this->patientFor($owner);
        $this->recordFor($owner, $patient, '2026-04-10');

        Storage::shouldReceive('temporaryUrl')->never();

        $this->actingAs($intruder)
            ->postJson(route('medical-records.export'), ['patient_id' => encrypt($patient->id)])
            ->assertStatus(400)
            ->assertJson(['message' => __('medical-records.patient_not_found')]);
    }

    public function testItExportsWithoutCodeWhenTwoFactorIsEnabled(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional', 'two_factor' => 1, 'two_factor_secret' => 'ABCDEFGHIJKLMNOP']);
        $patient = $this->patientFor($user);
        $this->recordFor($user, $patient, '2026-04-10');

        Storage::shouldReceive('temporaryUrl')->once()->andReturn('https://s3.test/a');

        $this->actingAs($user)
            ->postJson(route('medical-records.export'), ['patient_id' => encrypt($patient->id)])
            ->assertOk()
            ->assertJsonCount(1, 'records');
    }

    public function testItDeletesWithoutCodeWhenTwoFactorIsEnabled(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional', 'two_factor' => 1, 'two_factor_secret' => 'ABCDEFGHIJKLMNOP']);
        $record = $this->recordFor($user, $this->patientFor($user), now()->subYears(6)->toDateString());

        $this->actingAs($user)->deleteJson(route('medical-records.delete'), ['ids' => [encrypt($record->id)]])
            ->assertOk();

        $this->assertSoftDeleted($record);
    }

    public function testItShowsExportActionOnPatientPage(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);
        $patient = $this->patientFor($user);

        $this->actingAs($user)->get(route('patients.show', ['id' => encrypt($patient->id)]))
            ->assertOk()
            ->assertSee('id="export-medical-records"', false);
    }
}
