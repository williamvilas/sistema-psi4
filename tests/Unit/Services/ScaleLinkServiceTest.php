<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AccessLogActionEnum;
use App\Enums\ScaleEnum;
use App\Exceptions\ScaleLinkUnavailableException;
use App\Models\Patients;
use App\Models\PatientScaleApplications;
use App\Models\PatientScaleLinks;
use App\Models\User;
use App\Models\UserEncryptionKeys;
use App\Services\AccessLogService;
use App\Services\FileService;
use App\Services\ScaleLinkService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ScaleLinkServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TOKEN = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJ0123456';
    private const PUBLIC_KEY = 'public-key-base64';

    private $scaleLinks;
    private $scaleApplications;
    private $patients;
    private $encryptionKeys;
    private $users;
    private $disk;
    private $accessLogService;
    private $service;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        Auth::login($this->user);

        $this->scaleLinks = Mockery::mock(PatientScaleLinks::class);
        $this->scaleApplications = Mockery::mock(PatientScaleApplications::class);
        $this->patients = Mockery::mock(Patients::class);
        $this->encryptionKeys = Mockery::mock(UserEncryptionKeys::class);
        $this->users = Mockery::mock(User::class);
        $this->accessLogService = Mockery::mock(AccessLogService::class);

        // FileService é readonly (não mockável): usa o real sobre o disco s3 mockado.
        $this->disk = Mockery::mock();
        Storage::shouldReceive('disk')->with('s3')->andReturn($this->disk);

        $this->service = new ScaleLinkService(
            $this->scaleLinks,
            $this->scaleApplications,
            $this->patients,
            $this->encryptionKeys,
            $this->users,
            new FileService(new Storage),
            $this->accessLogService,
        );
    }

    private function mockPatientExists(bool $exists = true, int $patientId = 10): void
    {
        $this->patients->shouldReceive('findById')
            ->with($patientId, $this->user->id, ['id'])
            ->andReturn($exists ? (new Patients)->forceFill(['id' => $patientId]) : null);
    }

    private function mockPublicKey(?string $publicKey = self::PUBLIC_KEY): void
    {
        $this->encryptionKeys->shouldReceive('findScalePublicKeyByUser')->with($this->user->id)->andReturn($publicKey);
    }

    // Model real (atributos, casts e regras); só o save() não toca no banco e conta as chamadas.
    private function makeLink(array $attributes = [], bool $saves = true): PatientScaleLinks
    {
        $link = new class extends PatientScaleLinks
        {
            public bool $saves = true;

            public int $saveCalls = 0;

            public function save(array $options = []): bool
            {
                $this->saveCalls++;

                return $this->saves;
            }
        };

        $link->saves = $saves;
        $link->forceFill([
            'id' => 5,
            'user_id' => $this->user->id,
            'patient_id' => 10,
            'scale' => ScaleEnum::PHQ9,
            'expires_at' => now()->addDays(3),
            'completed_at' => null,
            'revoked_at' => null,
            'content_path' => null,
            ...$attributes,
        ]);

        return $link;
    }

    private function mockFindByToken(?PatientScaleLinks $link, bool $lock = false): void
    {
        $this->scaleLinks->shouldReceive('findByTokenHash')
            ->with(ScaleLinkService::hashToken(self::TOKEN), ['*'], $lock)
            ->andReturn($link);
    }

    public function testItStoreCreatesLinkWithTokenHashAndExpiration(): void
    {
        // Arrange
        $this->mockPatientExists();
        $this->mockPublicKey();
        $this->scaleLinks->shouldReceive('findTodayByPatientAndScale')->once()->andReturn(null);
        $created = new PatientScaleLinks;
        $this->scaleLinks->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) {
                return $data['user_id'] === $this->user->id
                    && $data['patient_id'] === 10
                    && $data['scale'] === 'phq9'
                    && strlen($data['token']) === 43
                    && $data['token_hash'] === hash('sha256', $data['token'])
                    && $data['expires_at']->between(now()->addDays(PatientScaleLinks::VALID_DAYS)->subMinute(), now()->addDays(PatientScaleLinks::VALID_DAYS)->addMinute());
            }))
            ->andReturn($created);

        // Act
        $result = $this->service->store(10, 'phq9', $this->user->id, 'America/Sao_Paulo');

        // Assert
        $this->assertSame($created, $result);
    }

    public function testItStoreUsesStartOfDayInPsychologistTimezone(): void
    {
        // Arrange
        $this->mockPatientExists();
        $this->mockPublicKey();
        $expected = now('America/Sao_Paulo')->startOfDay()->utc();
        $this->scaleLinks->shouldReceive('findTodayByPatientAndScale')
            ->once()
            ->with($this->user->id, 10, 'gad7', Mockery::on(fn ($since) => $since->equalTo($expected)))
            ->andReturn(null);
        $this->scaleLinks->shouldReceive('create')->once()->andReturn(new PatientScaleLinks);

        // Act
        $this->service->store(10, 'gad7', $this->user->id, 'America/Sao_Paulo');

        // Assert
        $this->addToAssertionCount(1);
    }

    public function testItStoreReturnsPendingLinkOfTodayInsteadOfCreatingAnother(): void
    {
        // Arrange
        $this->mockPatientExists();
        $this->mockPublicKey();
        $pending = $this->makeLink();
        $this->scaleLinks->shouldReceive('findTodayByPatientAndScale')->once()->andReturn($pending);
        $this->scaleLinks->shouldNotReceive('create');

        // Act
        $result = $this->service->store(10, 'phq9', $this->user->id, 'America/Sao_Paulo');

        // Assert
        $this->assertSame($pending, $result);
    }

    public function testItStoreThrowExceptionWhenAlreadyAnsweredToday(): void
    {
        // Arrange
        $this->mockPatientExists();
        $this->mockPublicKey();
        $this->scaleLinks->shouldReceive('findTodayByPatientAndScale')->once()->andReturn($this->makeLink(['completed_at' => now()]));
        $this->scaleLinks->shouldNotReceive('create');

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('scales.link_daily_limit'));

        // Act
        $this->service->store(10, 'phq9', $this->user->id, 'America/Sao_Paulo');
    }

    public function testItStoreThrowExceptionWhenPatientBelongsToAnotherUser(): void
    {
        // Arrange
        $this->mockPatientExists(false);
        $this->scaleLinks->shouldNotReceive('create');

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('scales.patient_not_found'));

        // Act
        $this->service->store(10, 'phq9', $this->user->id, 'America/Sao_Paulo');
    }

    public function testItStoreThrowExceptionWithoutScalePublicKey(): void
    {
        // Arrange
        $this->mockPatientExists();
        $this->mockPublicKey(null);
        $this->scaleLinks->shouldNotReceive('create');

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('scales.scale_key_required'));

        // Act
        $this->service->store(10, 'phq9', $this->user->id, 'America/Sao_Paulo');
    }

    public function testItPaginateByPatientSuccessfully(): void
    {
        // Arrange
        $this->mockPatientExists();
        $paginator = Mockery::mock(LengthAwarePaginator::class);
        $this->scaleLinks->shouldReceive('paginateByPatient')
            ->once()
            ->with($this->user->id, 10, 5, ['id', 'scale', 'token', 'expires_at', 'completed_at', 'revoked_at', 'created_at'])
            ->andReturn($paginator);

        // Act
        $result = $this->service->paginateByPatient(10, $this->user->id, 5);

        // Assert
        $this->assertSame($paginator, $result);
    }

    public function testItPaginateByPatientThrowExceptionWhenPatientBelongsToAnotherUser(): void
    {
        // Arrange
        $this->mockPatientExists(false);
        $this->scaleLinks->shouldNotReceive('paginateByPatient');

        // Assert
        $this->expectException(\DomainException::class);

        // Act
        $this->service->paginateByPatient(10, $this->user->id, 5);
    }

    public function testItRevokeMarksLinkAsRevoked(): void
    {
        // Arrange
        $link = $this->makeLink();
        $this->scaleLinks->shouldReceive('findById')->once()->with(5, $this->user->id)->andReturn($link);

        // Act
        $this->service->revoke(5, $this->user->id);

        // Assert
        $this->assertSame(1, $link->saveCalls);
        $this->assertNotNull($link->revoked_at);
        $this->assertFalse($link->isPending());
    }

    public function testItRevokeIgnoresAlreadyRevokedLink(): void
    {
        // Arrange
        $revokedAt = now()->subHour()->startOfSecond();
        $link = $this->makeLink(['revoked_at' => $revokedAt]);
        $this->scaleLinks->shouldReceive('findById')->once()->andReturn($link);

        // Act
        $this->service->revoke(5, $this->user->id);

        // Assert
        $this->assertSame(0, $link->saveCalls);
        $this->assertTrue($link->revoked_at->equalTo($revokedAt));
    }

    public function testItRevokeThrowExceptionWhenLinkNotFound(): void
    {
        // Arrange
        $this->scaleLinks->shouldReceive('findById')->once()->andReturn(null);

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('scales.link_not_found'));

        // Act
        $this->service->revoke(5, $this->user->id);
    }

    public function testItRevokeThrowExceptionWhenAlreadyAnswered(): void
    {
        // Arrange
        $this->scaleLinks->shouldReceive('findById')->once()->andReturn($this->makeLink(['completed_at' => now()]));

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('scales.link_already_answered'));

        // Act
        $this->service->revoke(5, $this->user->id);
    }

    public function testItRevokeThrowExceptionWhenSaveFails(): void
    {
        // Arrange
        $this->scaleLinks->shouldReceive('findById')->once()->andReturn($this->makeLink([], false));

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('messages.failed_update'));

        // Act
        $this->service->revoke(5, $this->user->id);
    }

    public function testItFindPublicReturnsOnlyQuestionsAndPublicKey(): void
    {
        // Arrange
        $this->mockFindByToken($this->makeLink());
        $this->mockPatientExists();
        $this->mockPublicKey();

        // Act
        $result = $this->service->findPublic(self::TOKEN);

        // Assert: nada de faixas, pontuação ou orientações clínicas para a psicóloga
        $this->assertSame(self::PUBLIC_KEY, $result['public_key']);
        $this->assertSame(['slug', 'name', 'version', 'instruction', 'items', 'options', 'reference_event'], array_keys($result['scale']));
        $this->assertSame('phq9', $result['scale']['slug']);
        $this->assertCount(9, $result['scale']['items']);
        $this->assertFalse($result['scale']['reference_event']);
    }

    public function testItFindPublicThrowExceptionWhenLinkDoesNotExist(): void
    {
        // Arrange
        $this->mockFindByToken(null);

        // Assert
        $this->expectException(ScaleLinkUnavailableException::class);

        // Act
        $this->service->findPublic(self::TOKEN);
    }

    public function testItFindPublicThrowExceptionWhenLinkExpired(): void
    {
        // Arrange
        $this->mockFindByToken($this->makeLink(['expires_at' => now()->subMinute()]));

        // Assert
        $this->expectException(ScaleLinkUnavailableException::class);

        // Act
        $this->service->findPublic(self::TOKEN);
    }

    public function testItFindPublicThrowExceptionWhenLinkAlreadyAnswered(): void
    {
        // Arrange
        $this->mockFindByToken($this->makeLink(['completed_at' => now()]));

        // Assert
        $this->expectException(ScaleLinkUnavailableException::class);

        // Act
        $this->service->findPublic(self::TOKEN);
    }

    public function testItFindPublicThrowExceptionWhenLinkRevoked(): void
    {
        // Arrange
        $this->mockFindByToken($this->makeLink(['revoked_at' => now()]));

        // Assert
        $this->expectException(ScaleLinkUnavailableException::class);

        // Act
        $this->service->findPublic(self::TOKEN);
    }

    public function testItFindPublicThrowExceptionWhenPatientWasRemoved(): void
    {
        // Arrange
        $this->mockFindByToken($this->makeLink());
        $this->mockPatientExists(false);

        // Assert
        $this->expectException(ScaleLinkUnavailableException::class);

        // Act
        $this->service->findPublic(self::TOKEN);
    }

    public function testItFindPublicThrowExceptionWithoutPublicKey(): void
    {
        // Arrange
        $this->mockFindByToken($this->makeLink());
        $this->mockPatientExists();
        $this->mockPublicKey(null);

        // Assert
        $this->expectException(ScaleLinkUnavailableException::class);

        // Act
        $this->service->findPublic(self::TOKEN);
    }

    public function testItIssueUploadUrlReservesContentPath(): void
    {
        // Arrange
        $link = $this->makeLink();
        $this->mockFindByToken($link);
        $this->mockPatientExists();
        $this->disk->shouldReceive('exists')->once()->andReturn(false);
        $this->disk->shouldReceive('temporaryUploadUrl')
            ->once()
            // Chamada com argumentos nomeados: compara pelos valores, na ordem da assinatura.
            ->withArgs(function (...$args) {
                [$path, , $options] = array_values($args);

                return preg_match('/^scale-applications\/[0-9a-f-]{36}\.bin$/', $path) === 1 && $options === ['ContentType' => 'application/octet-stream'];
            })
            ->andReturn(['url' => 'https://s3.test/upload', 'headers' => []]);

        // Act
        $result = $this->service->issueUploadUrl(self::TOKEN);

        // Assert
        $this->assertSame('https://s3.test/upload', $result['link']);
        $this->assertSame($link->content_path, $result['content_path']);
        $this->assertMatchesRegularExpression('/^scale-applications\/[0-9a-f-]{36}\.bin$/', $result['content_path']);
    }

    public function testItIssueUploadUrlThrowExceptionWhenLinkUnavailable(): void
    {
        // Arrange
        $this->mockFindByToken($this->makeLink(['completed_at' => now()]));
        $this->disk->shouldNotReceive('temporaryUploadUrl');

        // Assert
        $this->expectException(ScaleLinkUnavailableException::class);

        // Act
        $this->service->issueUploadUrl(self::TOKEN);
    }

    public function testItCompleteCreatesApplicationClosesLinkAndRegistersAccess(): void
    {
        // Arrange
        $link = $this->makeLink(['content_path' => 'scale-applications/abc.bin']);
        $this->mockFindByToken($link, true);
        $this->mockPatientExists();
        $this->disk->shouldReceive('exists')->once()->with('scale-applications/abc.bin')->andReturn(true);
        $this->users->shouldReceive('findById')->once()->with($this->user->id)->andReturn($this->user);
        $application = (new PatientScaleApplications)->forceFill(['id' => 77]);
        $this->scaleApplications->shouldReceive('create')
            ->once()
            ->with([
                'user_id' => $this->user->id,
                'patient_id' => 10,
                'scale' => 'phq9',
                'applied_on' => now('America/Sao_Paulo')->toDateString(),
                'content_path' => 'scale-applications/abc.bin',
                'encryption' => 'ecdh',
            ])
            ->andReturn($application);
        $this->accessLogService->shouldReceive('registerScaleApplications')
            ->once()
            ->with($this->user->id, AccessLogActionEnum::SCALE_APPLICATION_ANSWERED, [77]);

        // Act
        $result = $this->service->complete(self::TOKEN);

        // Assert
        $this->assertSame($application, $result);
        $this->assertNotNull($link->completed_at);
        $this->assertSame(77, $link->scale_application_id);
        $this->assertFalse($link->isPending());
    }

    public function testItCompleteThrowExceptionWhenUploadWasNotRequested(): void
    {
        // Arrange
        $this->mockFindByToken($this->makeLink(), true);
        $this->mockPatientExists();
        $this->scaleApplications->shouldNotReceive('create');

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('scales.link_upload_missing'));

        // Act
        $this->service->complete(self::TOKEN);
    }

    public function testItCompleteThrowExceptionWhenFileIsMissingInStorage(): void
    {
        // Arrange
        $this->mockFindByToken($this->makeLink(['content_path' => 'scale-applications/abc.bin']), true);
        $this->mockPatientExists();
        $this->disk->shouldReceive('exists')->once()->andReturn(false);
        $this->scaleApplications->shouldNotReceive('create');

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('scales.link_upload_missing'));

        // Act
        $this->service->complete(self::TOKEN);
    }

    public function testItCompleteThrowExceptionWhenAlreadyAnswered(): void
    {
        // Arrange
        $this->mockFindByToken($this->makeLink(['completed_at' => now(), 'content_path' => 'scale-applications/abc.bin']), true);
        $this->scaleApplications->shouldNotReceive('create');
        $this->accessLogService->shouldNotReceive('registerScaleApplications');

        // Assert
        $this->expectException(ScaleLinkUnavailableException::class);

        // Act
        $this->service->complete(self::TOKEN);
    }

    public function testItCompleteThrowExceptionWhenClosingLinkFails(): void
    {
        // Arrange
        $this->mockFindByToken($this->makeLink(['content_path' => 'scale-applications/abc.bin'], false), true);
        $this->mockPatientExists();
        $this->disk->shouldReceive('exists')->andReturn(true);
        $this->users->shouldReceive('findById')->andReturn($this->user);
        $this->scaleApplications->shouldReceive('create')->once()->andReturn((new PatientScaleApplications)->forceFill(['id' => 77]));
        $this->accessLogService->shouldNotReceive('registerScaleApplications');

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('messages.failed_update'));

        // Act
        $this->service->complete(self::TOKEN);
    }
}
