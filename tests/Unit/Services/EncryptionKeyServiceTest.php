<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AccessLogActionEnum;
use App\Models\User;
use App\Models\UserEncryptionKeys;
use App\Services\AccessLogService;
use App\Services\EncryptionKeyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class EncryptionKeyServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const KEY_CHECK = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private $userEncryptionKeys;
    private $accessLogService;
    private $service;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Auth::login($this->user);

        $this->userEncryptionKeys = Mockery::mock(UserEncryptionKeys::class);
        $this->accessLogService = Mockery::mock(AccessLogService::class);

        $this->service = new EncryptionKeyService($this->userEncryptionKeys, $this->accessLogService);
    }

    private function wrapData(string $prefix): array
    {
        return [
            $prefix.'_wrapped_key' => str_repeat('A', 80),
            $prefix.'_salt' => str_repeat('B', 22).'==',
            $prefix.'_iterations' => 600000,
        ];
    }

    private function storedKey(string $keyCheck = self::KEY_CHECK): UserEncryptionKeys
    {
        $key = Mockery::mock(UserEncryptionKeys::class.'[save]');
        $key->key_check = hash('sha256', $keyCheck);

        return $key;
    }

    public function testItExistsReturnsModelResult(): void
    {
        // Arrange
        $this->userEncryptionKeys->shouldReceive('existsByUser')->once()->with($this->user->id)->andReturnTrue();

        // Act / Assert
        $this->assertTrue($this->service->exists($this->user->id));
    }

    public function testItDetailsReturnsOnlyWrapsWithoutKeyCheck(): void
    {
        // Arrange
        $key = new UserEncryptionKeys([...$this->wrapData('passphrase'), ...$this->wrapData('recovery')]);
        $this->userEncryptionKeys->shouldReceive('findByUser')
            ->once()
            ->with($this->user->id, Mockery::on(fn ($cols) => ! in_array('key_check', $cols, true)))
            ->andReturn($key);

        // Act
        $details = $this->service->details($this->user->id);

        // Assert
        $this->assertSame(600000, $details['passphrase_iterations']);
        $this->assertArrayNotHasKey('key_check', $details);
    }

    public function testItDetailsReturnsNullWhenKeyDoesNotExist(): void
    {
        // Arrange
        $this->userEncryptionKeys->shouldReceive('findByUser')->once()->andReturnNull();

        // Act / Assert
        $this->assertNull($this->service->details($this->user->id));
    }

    public function testItStoreSuccessfullyHashingKeyCheckAndRegisteringLog(): void
    {
        // Arrange
        $data = [...$this->wrapData('passphrase'), ...$this->wrapData('recovery'), 'key_check' => self::KEY_CHECK];

        $this->userEncryptionKeys->shouldReceive('existsByUser')->once()->andReturnFalse();
        $this->userEncryptionKeys->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($values) => $values['user_id'] === $this->user->id
                && $values['key_check'] === hash('sha256', self::KEY_CHECK)
                && $values['recovery_wrapped_key'] === $data['recovery_wrapped_key']))
            ->andReturn(new UserEncryptionKeys());

        $this->accessLogService->shouldReceive('register')
            ->once()
            ->with($this->user->id, AccessLogActionEnum::ENCRYPTION_KEY_CREATED);

        // Act
        $this->service->store($this->user->id, $data);

        // Assert
        $this->addToAssertionCount(1);
    }

    public function testItStoreThrowExceptionWhenKeyAlreadyExists(): void
    {
        // Arrange
        $this->userEncryptionKeys->shouldReceive('existsByUser')->once()->andReturnTrue();
        $this->userEncryptionKeys->shouldReceive('create')->never();
        $this->accessLogService->shouldReceive('register')->never();

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('encryption-key.already_exists'));

        // Act
        $this->service->store($this->user->id, ['key_check' => self::KEY_CHECK]);
    }

    public function testItUpdatePassphraseSuccessfully(): void
    {
        // Arrange
        $key = $this->storedKey();
        $key->shouldReceive('save')->once()->andReturnTrue();
        $this->userEncryptionKeys->shouldReceive('findByUser')->once()->with($this->user->id)->andReturn($key);
        $this->accessLogService->shouldReceive('register')
            ->once()
            ->with($this->user->id, AccessLogActionEnum::ENCRYPTION_KEY_PASSPHRASE_CHANGED);

        // Act
        $this->service->updatePassphrase($this->user->id, [...$this->wrapData('passphrase'), 'key_check' => strtoupper(self::KEY_CHECK)]);

        // Assert
        $this->assertSame(str_repeat('A', 80), $key->passphrase_wrapped_key);
    }

    public function testItUpdateRecoverySuccessfully(): void
    {
        // Arrange
        $key = $this->storedKey();
        $key->shouldReceive('save')->once()->andReturnTrue();
        $this->userEncryptionKeys->shouldReceive('findByUser')->once()->andReturn($key);
        $this->accessLogService->shouldReceive('register')
            ->once()
            ->with($this->user->id, AccessLogActionEnum::ENCRYPTION_KEY_RECOVERY_CHANGED);

        // Act
        $this->service->updateRecovery($this->user->id, [...$this->wrapData('recovery'), 'key_check' => self::KEY_CHECK]);

        // Assert
        $this->assertSame(600000, $key->recovery_iterations);
        $this->assertNull($key->passphrase_wrapped_key);
    }

    public function testItUpdatePassphraseThrowExceptionWhenKeyCheckDoesNotMatch(): void
    {
        // Arrange
        $key = $this->storedKey();
        $key->shouldReceive('save')->never();
        $this->userEncryptionKeys->shouldReceive('findByUser')->once()->andReturn($key);
        $this->accessLogService->shouldReceive('register')->never();

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('encryption-key.invalid_key'));

        // Act
        $this->service->updatePassphrase($this->user->id, [...$this->wrapData('passphrase'), 'key_check' => str_repeat('0', 64)]);
    }

    public function testItUpdatePassphraseThrowExceptionWhenKeyDoesNotExist(): void
    {
        // Arrange
        $this->userEncryptionKeys->shouldReceive('findByUser')->once()->andReturnNull();
        $this->accessLogService->shouldReceive('register')->never();

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('encryption-key.not_found'));

        // Act
        $this->service->updatePassphrase($this->user->id, [...$this->wrapData('passphrase'), 'key_check' => self::KEY_CHECK]);
    }

    public function testItUpdatePassphraseThrowExceptionWhenSaveFails(): void
    {
        // Arrange
        $key = $this->storedKey();
        $key->shouldReceive('save')->once()->andReturnFalse();
        $this->userEncryptionKeys->shouldReceive('findByUser')->once()->andReturn($key);
        $this->accessLogService->shouldReceive('register')->never();

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('errors.update_failed'));

        // Act
        $this->service->updatePassphrase($this->user->id, [...$this->wrapData('passphrase'), 'key_check' => self::KEY_CHECK]);
    }
}
