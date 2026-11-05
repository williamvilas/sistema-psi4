<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Enums\FeatureUsageEnum;
use App\Jobs\SaveFeatureUsageJob;
use App\Models\Logs;
use App\Models\User;
use App\Models\UserFeatureUsages;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class SaveFeatureUsageJobTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function usageCount(FeatureUsageEnum $feature): ?int
    {
        return UserFeatureUsages::query()
            ->where('user_id', $this->user->id)
            ->where('feature', $feature->value)
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->value('usage_count');
    }

    public function testItSaveFeatureUsageSuccessfully(): void
    {
        // Act
        SaveFeatureUsageJob::dispatchSync(userId: $this->user->id, feature: FeatureUsageEnum::DOCUMENTS);

        // Assert
        $this->assertSame(1, $this->usageCount(FeatureUsageEnum::DOCUMENTS));
    }

    public function testItIncrementUsageCountWhenFeatureAlreadyUsedInMonth(): void
    {
        // Act
        SaveFeatureUsageJob::dispatchSync(userId: $this->user->id, feature: FeatureUsageEnum::PUBLIC_BOOKING);
        SaveFeatureUsageJob::dispatchSync(userId: $this->user->id, feature: FeatureUsageEnum::PUBLIC_BOOKING);

        // Assert
        $this->assertSame(2, $this->usageCount(FeatureUsageEnum::PUBLIC_BOOKING));
        $this->assertNull($this->usageCount(FeatureUsageEnum::CREATE_PAYMENT_ORDER));
    }

    public function testItNotThrowExceptionAndRegisterLogWhenUpsertFails(): void
    {
        // Arrange
        $featureUsages = Mockery::mock(UserFeatureUsages::class);
        $featureUsages->shouldReceive('upsert')->once()->andThrow(new \RuntimeException('db indisponível'));

        $job = new SaveFeatureUsageJob(userId: $this->user->id, feature: FeatureUsageEnum::SEARCH_REPORT);

        // Act
        $job->handle($featureUsages);

        // Assert
        $log = Logs::query()->where('message', 'like', 'Falha ao salvar o uso da feature%')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('db indisponível', $log->message);
    }
}
