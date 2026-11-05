<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FeatureUsageEnum;
use App\Models\Patients;
use App\Models\PatientScaleApplications;
use App\Models\User;
use App\Models\UserEncryptionKeys;
use App\Models\UserFeatureUsages;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PlanLimitTest extends TestCase
{
    use DatabaseTransactions;

    private function userWithKey(string $plan = 'free'): User
    {
        $user = User::factory()->create(['current_plan' => $plan]);
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

    private function setUsage(User $user, FeatureUsageEnum $feature, int $count): void
    {
        UserFeatureUsages::query()->create([
            'user_id' => $user->id,
            'feature' => $feature->value,
            'year' => now()->year,
            'month' => now()->month,
            'usage_count' => $count,
        ]);
    }

    private function storeScale(User $user): \Illuminate\Testing\TestResponse
    {
        $patient = Patients::query()->create(['user_id' => $user->id, 'name' => 'Maria Teste', 'phone' => '119'.random_int(10000000, 99999999)]);

        return $this->actingAs($user)->postJson(route('scale-applications.store'), [
            'patient_id' => encrypt($patient->id),
            'scale' => 'gad7',
            'applied_on' => now()->format('d/m/Y'),
            'content_path' => 'scale-applications/'.uniqid().'.bin',
        ]);
    }

    public function testItBlocksCreationOverLimitWithUpgradeLink(): void
    {
        config(['plans.plans.free.total_scales' => 3]);
        $user = $this->userWithKey();
        $this->setUsage($user, FeatureUsageEnum::SCALE_LINKS, 3);

        $this->storeScale($user)
            ->assertForbidden()
            ->assertJsonPath('message', __('plans.limit_reached.total_scales'))
            ->assertJsonPath('link', route('plans.index', [], false));

        $this->assertSame(0, PatientScaleApplications::query()->where('user_id', $user->id)->count());
    }

    public function testItAllowsCreationBelowLimitAndCountsUsage(): void
    {
        config(['plans.plans.free.total_scales' => 3]);
        $user = $this->userWithKey();

        $this->storeScale($user)->assertOk();

        $this->assertSame(1, (int) UserFeatureUsages::query()->where('user_id', $user->id)->where('feature', FeatureUsageEnum::SCALE_APPLICATIONS->value)->value('usage_count'));
    }

    public function testItAllowsCreationWithoutLimitOnProfessionalPlan(): void
    {
        $user = $this->userWithKey('professional');
        $this->setUsage($user, FeatureUsageEnum::SCALE_APPLICATIONS, 50);

        $this->storeScale($user)->assertOk();
    }

    public function testItAllowsPatientsWithoutLimitOnFreePlan(): void
    {
        $user = User::factory()->create(['current_plan' => 'free']);
        $this->setUsage($user, FeatureUsageEnum::PATIENTS, 100);

        // A resposta vem da validação do cadastro, nunca do limite do plano
        $this->assertNotSame(403, $this->actingAs($user)->postJson(route('patients.store'), [])->getStatusCode());
    }

    public function testItShowsLimitNoticeBeforeWritingMedicalRecord(): void
    {
        config(['plans.plans.free.total_medical_records' => 3]);
        $user = $this->userWithKey();
        $this->setUsage($user, FeatureUsageEnum::MEDICAL_RECORDS, 3);

        $this->actingAs($user)->get(route('medical-records.create'))
            ->assertOk()
            ->assertSee('Seu plano chegou ao limite de prontuários deste mês')
            ->assertDontSee('medical-editor.js');
    }

    public function testItShowsUsageBannerWhenLimitReached(): void
    {
        config(['plans.plans.free.total_agenda' => 3]);
        $user = $this->userWithKey();
        $this->setUsage($user, FeatureUsageEnum::AGENDA, 3);

        $this->actingAs($user)->get(route('plans.index'))
            ->assertOk()
            ->assertSee('Seu plano Grátis chegou ao limite deste mês em: agendamentos (3 de 3)', false)
            ->assertSee('Seu link de agendamento online também fica pausado');
    }
}
