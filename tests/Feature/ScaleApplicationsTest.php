<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AccessLogs;
use App\Models\Patients;
use App\Models\PatientScaleApplications;
use App\Models\User;
use App\Models\UserEncryptionKeys;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScaleApplicationsTest extends TestCase
{
    use DatabaseTransactions;

    private function userWithKey(): User
    {
        $user = User::factory()->create(['current_plan' => 'professional']);
        UserEncryptionKeys::query()->create([
            'user_id' => $user->id,
            'passphrase_wrapped_key' => str_repeat('A', 80),
            'passphrase_salt' => base64_encode(random_bytes(16)),
            'passphrase_iterations' => 600000,
            'recovery_wrapped_key' => str_repeat('B', 80),
            'recovery_salt' => base64_encode(random_bytes(16)),
            'recovery_iterations' => 600000,
            'key_check' => hash('sha256', 'check'),
        ]);

        return $user;
    }

    private function patientFor(User $user, string $name = 'Maria Teste'): Patients
    {
        return Patients::query()->create(['user_id' => $user->id, 'name' => $name, 'phone' => '119'.random_int(10000000, 99999999)]);
    }

    private function applicationFor(User $user, Patients $patient, string $appliedOn, string $scale = 'phq9'): PatientScaleApplications
    {
        return PatientScaleApplications::query()->create([
            'user_id' => $user->id,
            'patient_id' => $patient->id,
            'scale' => $scale,
            'applied_on' => $appliedOn,
            'content_path' => 'scale-applications/'.uniqid().'.bin',
        ]);
    }

    public function testItStoresApplicationOnFreePlanAndRegistersAccess(): void
    {
        $user = $this->userWithKey();
        $patient = $this->patientFor($user);

        $this->actingAs($user)->postJson(route('scale-applications.store'), [
            'patient_id' => encrypt($patient->id),
            'scale' => 'gad7',
            'applied_on' => now()->format('d/m/Y'),
            'content_path' => 'scale-applications/20261018-abc.bin',
        ])->assertOk();

        $application = PatientScaleApplications::query()->where('patient_id', $patient->id)->firstOrFail();
        $this->assertSame('gad7', $application->scale->value);
        $this->assertTrue(AccessLogs::query()->where('scale_application_id', $application->id)->where('action', 'scale_application_created')->exists());
    }

    public function testItStoresNewScales(): void
    {
        $user = $this->userWithKey();
        $patient = $this->patientFor($user);

        foreach (['dass21', 'pcl5'] as $scale) {
            $this->actingAs($user)->postJson(route('scale-applications.store'), [
                'patient_id' => encrypt($patient->id),
                'scale' => $scale,
                'applied_on' => now()->format('d/m/Y'),
                'content_path' => 'scale-applications/'.$scale.'.bin',
            ])->assertOk();
        }

        $this->assertSame(['dass21', 'pcl5'], PatientScaleApplications::query()->where('patient_id', $patient->id)->orderBy('id')->pluck('scale')->map->value->all());
    }

    public function testItRejectsUnknownScale(): void
    {
        $user = $this->userWithKey();

        $this->actingAs($user)->postJson(route('scale-applications.store'), [
            'patient_id' => encrypt($this->patientFor($user)->id),
            'scale' => 'who5',
            'applied_on' => now()->format('d/m/Y'),
            'content_path' => 'scale-applications/x.bin',
        ])->assertStatus(422)->assertJsonValidationErrors('scale');
    }

    public function testItDoesNotStoreForAnotherUsersPatient(): void
    {
        $owner = $this->userWithKey();
        $intruder = $this->userWithKey();
        $patient = $this->patientFor($owner);

        $this->actingAs($intruder)->postJson(route('scale-applications.store'), [
            'patient_id' => encrypt($patient->id),
            'scale' => 'phq9',
            'applied_on' => now()->format('d/m/Y'),
            'content_path' => 'scale-applications/x.bin',
        ])->assertStatus(400)->assertJson(['message' => __('scales.patient_not_found')]);

        $this->assertFalse(PatientScaleApplications::query()->where('patient_id', $patient->id)->exists());
    }

    public function testItListsOnlyPatientApplicationsInChronologicalOrderAndRegistersAccess(): void
    {
        $user = $this->userWithKey();
        $patient = $this->patientFor($user);
        $other = $this->patientFor($user, 'Outro');
        $second = $this->applicationFor($user, $patient, '2026-09-10');
        $first = $this->applicationFor($user, $patient, '2026-08-10', 'gad7');
        $this->applicationFor($user, $other, '2026-08-01');

        Storage::shouldReceive('temporaryUrl')->twice()->andReturn('https://s3.test/a', 'https://s3.test/b');

        $response = $this->actingAs($user)
            ->postJson(route('scale-applications.list'), ['patient_id' => encrypt($patient->id)])
            ->assertOk();

        $response->assertJsonCount(2, 'applications');
        $response->assertJsonPath('applications.0.scale', 'gad7');
        $response->assertJsonPath('applications.0.applied_on', '10/08/2026');
        $response->assertJsonPath('applications.0.content_path', $first->content_path);
        $response->assertJsonPath('applications.1.scale', 'phq9');

        $viewed = AccessLogs::query()->where('user_id', $user->id)->where('action', 'scale_application_viewed')->pluck('scale_application_id')->sort()->values()->all();
        $this->assertSame(collect([$first->id, $second->id])->sort()->values()->all(), $viewed);
    }

    public function testItDoesNotListAnotherUsersPatient(): void
    {
        $owner = $this->userWithKey();
        $intruder = $this->userWithKey();
        $patient = $this->patientFor($owner);
        $this->applicationFor($owner, $patient, '2026-09-10');

        Storage::shouldReceive('temporaryUrl')->never();

        $this->actingAs($intruder)
            ->postJson(route('scale-applications.list'), ['patient_id' => encrypt($patient->id)])
            ->assertStatus(400);
    }

    public function testItRequiresRetentionConfirmationToDelete(): void
    {
        $user = $this->userWithKey();
        $application = $this->applicationFor($user, $this->patientFor($user), now()->subYear()->toDateString());

        $this->actingAs($user)->deleteJson(route('scale-applications.delete'), ['id' => encrypt($application->id)])
            ->assertStatus(400)
            ->assertJson(['message' => __('scales.retention_confirmation_required')]);

        $this->assertNotSoftDeleted($application);
    }

    public function testItDeletesWhenRetentionIsConfirmed(): void
    {
        $user = $this->userWithKey();
        $application = $this->applicationFor($user, $this->patientFor($user), now()->subYear()->toDateString());

        $this->actingAs($user)->deleteJson(route('scale-applications.delete'), ['id' => encrypt($application->id), 'retention_confirmed' => 1])
            ->assertOk();

        $this->assertSoftDeleted($application);
        $this->assertTrue(AccessLogs::query()->where('scale_application_id', $application->id)->where('action', 'scale_application_deleted')->exists());
    }

    public function testItListsAndDeletesWithoutCodeWhenTwoFactorIsEnabled(): void
    {
        $user = $this->userWithKey();
        $user->forceFill(['two_factor' => 1, 'two_factor_secret' => 'ABCDEFGHIJKLMNOP'])->save();
        $patient = $this->patientFor($user);
        $application = $this->applicationFor($user, $patient, now()->subYear()->toDateString());

        Storage::shouldReceive('temporaryUrl')->once()->andReturn('https://s3.test/a');

        $this->actingAs($user)
            ->postJson(route('scale-applications.list'), ['patient_id' => encrypt($patient->id)])
            ->assertOk()
            ->assertJsonCount(1, 'applications');

        $this->actingAs($user)->deleteJson(route('scale-applications.delete'), ['id' => encrypt($application->id), 'retention_confirmed' => 1])
            ->assertOk();

        $this->assertSoftDeleted($application);
    }

    public function testItDoesNotDeleteAnotherUsersApplication(): void
    {
        $owner = $this->userWithKey();
        $intruder = $this->userWithKey();
        $application = $this->applicationFor($owner, $this->patientFor($owner), now()->subYears(6)->toDateString());

        $this->actingAs($intruder)->deleteJson(route('scale-applications.delete'), ['id' => encrypt($application->id), 'retention_confirmed' => 1])
            ->assertStatus(400)
            ->assertJson(['message' => __('scales.not_found')]);

        $this->assertNotSoftDeleted($application);
    }

    public function testItShowsEvolutionPanelOnPatientPage(): void
    {
        $user = $this->userWithKey();
        $patient = $this->patientFor($user);

        $this->actingAs($user)->get(route('patients.show', ['id' => encrypt($patient->id)]))
            ->assertOk()
            ->assertSee('id="patient-evolution"', false)
            ->assertSee('id="scales-catalog"', false)
            ->assertSee('PHQ-9');
    }

    public function testItReturnsEvolutionIndicatorsForOwnPatientOnly(): void
    {
        $owner = $this->userWithKey();
        $intruder = $this->userWithKey();
        $patient = $this->patientFor($owner);

        $this->actingAs($owner)->getJson(route('patients.evolution', ['id' => encrypt($patient->id), 'period' => 6]))
            ->assertOk()
            ->assertJsonPath('html', fn (string $html) => str_contains($html, 'Nenhuma sessão registrada neste período.'));

        $this->actingAs($intruder)->getJson(route('patients.evolution', ['id' => encrypt($patient->id), 'period' => 6]))
            ->assertStatus(400);
    }

    public function testItExportIncludesScalesAndRegistersExport(): void
    {
        $user = $this->userWithKey();
        $patient = $this->patientFor($user);
        $application = $this->applicationFor($user, $patient, '2026-09-10');

        Storage::shouldReceive('temporaryUrl')->once()->andReturn('https://s3.test/a');

        $this->actingAs($user)->postJson(route('medical-records.export'), ['patient_id' => encrypt($patient->id)])
            ->assertOk()
            ->assertJsonPath('scales.0.scale', 'phq9')
            ->assertJsonPath('scales.0.link', 'https://s3.test/a');

        $this->assertTrue(AccessLogs::query()->where('scale_application_id', $application->id)->where('action', 'scale_application_exported')->exists());
    }

    public function testItShowsScaleApplicationInAccessLogs(): void
    {
        $user = $this->userWithKey();
        $application = $this->applicationFor($user, $this->patientFor($user, 'Paciente Escala'), '2026-09-10');
        AccessLogs::query()->create(['user_id' => $user->id, 'action' => 'scale_application_viewed', 'scale_application_id' => $application->id]);

        $this->actingAs($user)->get(route('access-logs.index'))->assertOk();
        $html = $this->actingAs($user)->getJson(route('access-logs.paginate'))->assertOk()->json('html');

        $this->assertStringContainsString('Paciente Escala · PHQ-9', $html);
    }
}
