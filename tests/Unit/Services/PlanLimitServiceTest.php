<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\FeatureUsageEnum;
use App\Exceptions\PlanLimitException;
use App\Models\User;
use App\Models\UserFeatureUsages;
use App\Services\PlanLimitService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Usa o banco real: o que importa aqui é a soma por mês e a trava das linhas (lockForUpdate).
 */
class PlanLimitServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PlanLimitService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'plans.plans.free.total_agenda' => 3,
            'plans.plans.free.total_medical_records' => 3,
            'plans.plans.free.total_documents_issued' => 3,
            'plans.plans.free.total_scales' => 3,
            'plans.plans.free.total_payment_orders' => 3,
        ]);

        $this->user = User::factory()->create(['current_plan' => 'free']);
        Auth::login($this->user);

        $this->service = new PlanLimitService(new UserFeatureUsages, new User);
    }

    private function setUsage(FeatureUsageEnum $feature, int $count, ?int $year = null, ?int $month = null): void
    {
        UserFeatureUsages::query()->create([
            'user_id' => $this->user->id,
            'feature' => $feature->value,
            'year' => $year ?? now()->year,
            'month' => $month ?? now()->month,
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

    public function testItConsumeIncrementsUsageBelowLimit(): void
    {
        $this->service->consume($this->user->id, FeatureUsageEnum::MEDICAL_RECORDS);
        $this->service->consume($this->user->id, FeatureUsageEnum::MEDICAL_RECORDS);

        $this->assertSame(2, $this->usageOf(FeatureUsageEnum::MEDICAL_RECORDS));
    }

    public function testItConsumeThrowExceptionWhenLimitReached(): void
    {
        $this->setUsage(FeatureUsageEnum::MEDICAL_RECORDS, 3);

        try {
            $this->service->consume($this->user->id, FeatureUsageEnum::MEDICAL_RECORDS);
            $this->fail('Deveria lançar PlanLimitException');
        } catch (PlanLimitException $e) {
            $this->assertSame(__('plans.limit_reached.total_medical_records'), $e->getMessage());
        }

        $this->assertSame(3, $this->usageOf(FeatureUsageEnum::MEDICAL_RECORDS));
    }

    public function testItConsumeThrowExceptionWhenAmountPassesLimit(): void
    {
        $this->setUsage(FeatureUsageEnum::AGENDA, 2);

        $this->expectException(PlanLimitException::class);

        // Dois horários de uma vez com só um de saldo
        $this->service->consume($this->user->id, FeatureUsageEnum::AGENDA, 2);
    }

    public function testItConsumeCountsAmountForMultipleSlots(): void
    {
        $this->service->consume($this->user->id, FeatureUsageEnum::AGENDA, 3);

        $this->assertSame(3, $this->usageOf(FeatureUsageEnum::AGENDA));
    }

    public function testItConsumeSharesAgendaLimitWithPublicBooking(): void
    {
        $this->setUsage(FeatureUsageEnum::PUBLIC_BOOKING, 3);

        $this->expectException(PlanLimitException::class);

        $this->service->consume($this->user->id, FeatureUsageEnum::AGENDA);
    }

    public function testItConsumeSharesScalesLimitBetweenApplicationsAndLinks(): void
    {
        $this->setUsage(FeatureUsageEnum::SCALE_APPLICATIONS, 2);
        $this->service->consume($this->user->id, FeatureUsageEnum::SCALE_LINKS);

        $this->expectException(PlanLimitException::class);

        $this->service->consume($this->user->id, FeatureUsageEnum::SCALE_APPLICATIONS);
    }

    public function testItConsumeIgnoresPreviousMonthUsage(): void
    {
        $previous = now()->subMonthNoOverflow();
        $this->setUsage(FeatureUsageEnum::DOCUMENTS, 3, $previous->year, $previous->month);

        $this->service->consume($this->user->id, FeatureUsageEnum::DOCUMENTS);

        $this->assertSame(1, $this->usageOf(FeatureUsageEnum::DOCUMENTS));
    }

    public function testItConsumeWithoutLimitOnProfessionalPlan(): void
    {
        $this->user->update(['current_plan' => 'professional']);
        $this->setUsage(FeatureUsageEnum::CREATE_PAYMENT_ORDER, 50);

        $this->service->consume($this->user->id, FeatureUsageEnum::CREATE_PAYMENT_ORDER);

        $this->assertSame(51, $this->usageOf(FeatureUsageEnum::CREATE_PAYMENT_ORDER));
    }

    public function testItConsumeTracksFeatureWithoutLimit(): void
    {
        $this->setUsage(FeatureUsageEnum::PATIENTS, 100);

        $this->service->consume($this->user->id, FeatureUsageEnum::PATIENTS);

        $this->assertSame(101, $this->usageOf(FeatureUsageEnum::PATIENTS));
    }

    public function testItConsumeIsRolledBackWithOuterTransaction(): void
    {
        try {
            DB::transaction(function () {
                $this->service->consume($this->user->id, FeatureUsageEnum::MEDICAL_RECORDS);

                throw new \RuntimeException('falha ao criar o registro');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, $this->usageOf(FeatureUsageEnum::MEDICAL_RECORDS));
    }

    public function testItHasReachedReturnsTrueAtLimit(): void
    {
        $this->setUsage(FeatureUsageEnum::AGENDA, 1);
        $this->setUsage(FeatureUsageEnum::PUBLIC_BOOKING, 2);

        $this->assertTrue($this->service->hasReached($this->user->id, 'total_agenda'));
    }

    public function testItHasReachedReturnsFalseBelowLimit(): void
    {
        $this->setUsage(FeatureUsageEnum::MEDICAL_RECORDS, 2);

        $this->assertFalse($this->service->hasReached($this->user->id, 'total_medical_records'));
    }

    public function testItHasReachedReturnsFalseOnProfessionalPlan(): void
    {
        $this->user->update(['current_plan' => 'professional']);
        $this->setUsage(FeatureUsageEnum::MEDICAL_RECORDS, 30);

        $this->assertFalse($this->service->hasReached($this->user->id, 'total_medical_records'));
    }

    public function testItUsageReturnsLimitedKeysForFreePlan(): void
    {
        $this->setUsage(FeatureUsageEnum::AGENDA, 1);
        $this->setUsage(FeatureUsageEnum::PUBLIC_BOOKING, 1);

        $usage = $this->service->usage($this->user);

        $this->assertSame(['used' => 2, 'limit' => 3], $usage['total_agenda']);
        $this->assertSame(['used' => 0, 'limit' => 3], $usage['total_medical_records']);
        $this->assertSame(array_keys(PlanLimitService::LIMITS), array_keys($usage));
    }

    public function testItUsageReturnsEmptyOnProfessionalPlan(): void
    {
        $this->user->update(['current_plan' => 'professional']);

        $this->assertSame([], $this->service->usage($this->user));
    }

    public function testItLimitKeyOfReturnsNullForUntrackedFeature(): void
    {
        $this->assertSame('total_agenda', PlanLimitService::limitKeyOf(FeatureUsageEnum::PUBLIC_BOOKING));
        $this->assertNull(PlanLimitService::limitKeyOf(FeatureUsageEnum::CLICK_WHATSAPP));
    }
}
