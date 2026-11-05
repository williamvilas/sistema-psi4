<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AccessLogActionEnum;
use App\Models\AccessLogs;
use App\Models\User;
use App\Services\AccessLogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class AccessLogServiceTest extends TestCase
{
    use DatabaseTransactions;

    private $accessLogs;
    private $service;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Auth::login($this->user);

        $this->accessLogs = Mockery::mock(AccessLogs::class);
        $this->service = new AccessLogService($this->accessLogs);
    }

    public function testItRegisterSuccessfullyWithRequestData(): void
    {
        // Arrange
        $this->app['request']->server->set('REMOTE_ADDR', '200.10.20.30');
        $this->app['request']->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0');

        $this->accessLogs->shouldReceive('insertMany')
            ->once()
            ->with(Mockery::on(fn ($rows) => count($rows) === 1
                && $rows[0]['user_id'] === $this->user->id
                && $rows[0]['action'] === 'medical_record_viewed'
                && $rows[0]['medical_record_id'] === 15
                && $rows[0]['ip'] === '200.10.20.30'
                && str_contains($rows[0]['user_agent'], 'Chrome')))
            ->andReturnTrue();

        // Act
        $this->service->register($this->user->id, AccessLogActionEnum::MEDICAL_RECORD_VIEWED, 15);

        // Assert
        $this->addToAssertionCount(1);
    }

    public function testItRegisterWithoutMedicalRecord(): void
    {
        // Arrange
        $this->accessLogs->shouldReceive('insertMany')
            ->once()
            ->with(Mockery::on(fn ($rows) => $rows[0]['medical_record_id'] === null && $rows[0]['action'] === 'login'))
            ->andReturnTrue();

        // Act
        $this->service->register($this->user->id, AccessLogActionEnum::LOGIN);

        // Assert
        $this->addToAssertionCount(1);
    }

    public function testItRegisterManyCreatesOneRowPerRecord(): void
    {
        // Arrange
        $this->accessLogs->shouldReceive('insertMany')
            ->once()
            ->with(Mockery::on(fn ($rows) => array_column($rows, 'medical_record_id') === [1, 2, 3]))
            ->andReturnTrue();

        // Act
        $this->service->registerMany($this->user->id, AccessLogActionEnum::MEDICAL_RECORD_DELETED, [1, 2, 3]);

        // Assert
        $this->addToAssertionCount(1);
    }

    public function testItRegisterManyDoesNothingWithEmptyList(): void
    {
        // Arrange
        $this->accessLogs->shouldReceive('insertMany')->never();

        // Act
        $this->service->registerMany($this->user->id, AccessLogActionEnum::MEDICAL_RECORD_DELETED, []);

        // Assert
        $this->addToAssertionCount(1);
    }

    public function testItRegisterTruncatesLongUserAgent(): void
    {
        // Arrange
        $this->app['request']->headers->set('User-Agent', str_repeat('x', 600));

        $this->accessLogs->shouldReceive('insertMany')
            ->once()
            ->with(Mockery::on(fn ($rows) => mb_strlen($rows[0]['user_agent']) <= 255))
            ->andReturnTrue();

        // Act
        $this->service->register($this->user->id, AccessLogActionEnum::LOGIN);

        // Assert
        $this->addToAssertionCount(1);
    }

    public function testItPaginateDelegatesToModel(): void
    {
        // Arrange
        $paginator = new LengthAwarePaginator([], 0, 15);
        $params = ['action' => 'login'];

        $this->accessLogs->shouldReceive('paginateByUser')
            ->once()
            ->with($this->user->id, 15, $params)
            ->andReturn($paginator);

        // Act
        $result = $this->service->paginate($this->user->id, 15, $params);

        // Assert
        $this->assertSame($paginator, $result);
    }
}
