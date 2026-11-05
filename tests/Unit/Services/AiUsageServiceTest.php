<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\FeatureUsageEnum;
use App\Models\User;
use App\Models\UserFeatureUsages;
use App\Services\AiUsageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Usa o banco real: o que importa é a leitura do mês corrente em `user_feature_usages`.
 */
class AiUsageServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const SECRET = 'segredo-de-teste';

    private AiUsageService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'plans.plans.free.total_ai_tokens' => 1000,
            'plans.plans.professional.total_ai_tokens' => -1,
            'services.ai.grant_secret' => self::SECRET,
            'services.ai.grant_ttl' => 1800,
        ]);

        $this->user = User::factory()->create(['current_plan' => 'free']);
        Auth::login($this->user);
        $this->service = new AiUsageService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function testItReturnsZeroPercentAndGrantWithoutUsageInMonth(): void
    {
        $usage = $this->service->usage($this->user);

        $this->assertSame(0.0, $usage['percent']);
        $this->assertFalse($usage['reached']);
        $this->assertNull($usage['message']);
        $this->assertNotNull($usage['token']);
    }

    public function testItCalculatesPercentFromMonthUsage(): void
    {
        $this->consume(425);

        $usage = $this->service->usage($this->user);

        $this->assertSame(42.5, $usage['percent']);
        $this->assertFalse($usage['reached']);
    }

    public function testItBlocksWithoutTokenWhenLimitReachedAndCapsPercent(): void
    {
        $this->consume(1300);

        $usage = $this->service->usage($this->user);

        $this->assertSame(100.0, $usage['percent']);
        $this->assertTrue($usage['reached']);
        $this->assertNull($usage['token']);
        $this->assertSame(__('plans.ai_limit_reached.free'), $usage['message']);
    }

    public function testItRenewsWhenMonthTurnsWithoutUpdatingRows(): void
    {
        Carbon::setTestNow('2026-10-15 12:00:00');
        $this->consume(1000);
        $this->assertTrue($this->service->usage($this->user)['reached']);

        Carbon::setTestNow('2026-11-01 00:00:00');
        $usage = $this->service->usage($this->user);

        $this->assertSame(0.0, $usage['percent']);
        $this->assertFalse($usage['reached']);
    }

    public function testItIgnoresOtherFeaturesAndOtherUsers(): void
    {
        (new UserFeatureUsages)->upsert($this->user->id, FeatureUsageEnum::TRANSCRIPTION_MINUTES, 900);
        (new UserFeatureUsages)->upsert(User::factory()->create()->id, FeatureUsageEnum::AI_TOKENS, 900);

        $this->assertSame(0.0, $this->service->usage($this->user)['percent']);
    }

    public function testItNeverBlocksUnlimitedPlan(): void
    {
        $this->user->update(['current_plan' => 'professional']);
        $this->consume(5_000_000);

        $usage = $this->service->usage($this->user);

        $this->assertSame(0.0, $usage['percent']);
        $this->assertFalse($usage['reached']);
        $this->assertNotNull($usage['token']);
    }

    public function testItTreatsZeroLimitAsNotIncluded(): void
    {
        config(['plans.plans.free.total_ai_tokens' => 0]);

        $usage = $this->service->usage($this->user);

        $this->assertSame(100.0, $usage['percent']);
        $this->assertTrue($usage['reached']);
        $this->assertSame(__('plans.ai_limit_reached.not_included'), $usage['message']);
    }

    public function testItSignsGrantWithUserAndExpiration(): void
    {
        Carbon::setTestNow('2026-11-05 10:00:00');

        [$payload, $signature] = explode('.', $this->service->usage($this->user)['token']);

        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, self::SECRET, true)), '+/', '-_'), '=');
        $this->assertSame($expected, $signature);

        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $this->assertSame($this->user->id, $data['uid']);
        $this->assertSame($this->user->uuid, $data['uuid']);
        $this->assertSame(now()->addSeconds(1800)->getTimestamp(), $data['exp']);
    }

    public function testItThrowExceptionWhenGrantSecretIsMissing(): void
    {
        config(['services.ai.grant_secret' => '']);

        $this->expectException(\RuntimeException::class);

        $this->service->usage($this->user);
    }

    private function consume(int $tokens): void
    {
        (new UserFeatureUsages)->upsert($this->user->id, FeatureUsageEnum::AI_TOKENS, $tokens);
    }
}
