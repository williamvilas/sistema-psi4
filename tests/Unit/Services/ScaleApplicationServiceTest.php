<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AccessLogActionEnum;
use App\Enums\ScaleEncryptionEnum;
use App\Enums\ScaleEnum;
use App\Models\Patients;
use App\Models\PatientScaleApplications;
use App\Models\User;
use App\Models\UserEncryptionKeys;
use App\Services\AccessLogService;
use App\Services\EncryptionKeyService;
use App\Services\ScaleApplicationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ScaleApplicationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private $scaleApplications;
    private $patients;
    private $encryptionKeys;
    private $accessLogService;
    private $service;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Auth::login($this->user);

        $this->scaleApplications = Mockery::mock(PatientScaleApplications::class);
        $this->patients = Mockery::mock(Patients::class);
        $this->encryptionKeys = Mockery::mock(UserEncryptionKeys::class);
        $this->accessLogService = Mockery::mock(AccessLogService::class);

        // EncryptionKeyService é readonly (não mockável): usa o real sobre o model mockado.
        $this->service = new ScaleApplicationService(
            $this->scaleApplications,
            $this->patients,
            new EncryptionKeyService($this->encryptionKeys, $this->accessLogService),
            $this->accessLogService,
        );
    }

    private function mockPatientExists(bool $exists = true, int $patientId = 10): void
    {
        $this->patients->shouldReceive('findById')
            ->with($patientId, $this->user->id, ['id'])
            ->andReturn($exists ? (new Patients)->forceFill(['id' => $patientId]) : null);
    }

    private function makeApplication(int $id, ScaleEnum $scale, string $appliedOn, ScaleEncryptionEnum $encryption = ScaleEncryptionEnum::SYMMETRIC): PatientScaleApplications
    {
        return (new PatientScaleApplications)->forceFill([
            'id' => $id,
            'scale' => $scale,
            'applied_on' => Carbon::parse($appliedOn),
            'content_path' => 'scale-applications/'.$id.'.bin',
            'encryption' => $encryption,
        ]);
    }

    private function storeData(): array
    {
        return [
            'patient_id' => 10,
            'scale' => 'phq9',
            'applied_on' => '2026-10-18',
            'content_path' => 'scale-applications/abc.bin',
        ];
    }

    public function testItCatalogReturnsEveryScaleWithItemsOptionsAndRanges(): void
    {
        // Act
        $catalog = collect($this->service->catalog())->keyBy('slug');

        // Assert
        $this->assertSame(['phq9', 'gad7', 'dass21', 'pcl5'], $catalog->keys()->all());
        $this->assertCount(9, $catalog['phq9']['items']);
        $this->assertSame(27, $catalog['phq9']['max']);
        $this->assertSame(8, $catalog['phq9']['critical_item']);
        $this->assertSame('Item 9', $catalog['phq9']['critical_label']);
        $this->assertCount(7, $catalog['gad7']['items']);
        $this->assertSame(21, $catalog['gad7']['max']);
        $this->assertNull($catalog['gad7']['critical_item']);
        $this->assertCount(21, $catalog['dass21']['items']);
        $this->assertCount(3, $catalog['dass21']['subscales']);
        $this->assertCount(20, $catalog['pcl5']['items']);
        $this->assertCount(5, $catalog['pcl5']['options']);
        $this->assertSame(80, $catalog['pcl5']['max']);
        $this->assertTrue($catalog['pcl5']['reference_event']);
        $this->assertSame('PHQ-9', $catalog['phq9']['name']);
        $this->assertSame('DASS-21', $catalog['dass21']['name']);
        $this->assertSame('PCL-5', $catalog['pcl5']['name']);
    }

    public function testItCatalogRangesCoverEveryPossibleScore(): void
    {
        foreach ($this->service->catalog() as $scale) {
            // Arrange: com subescalas, cada uma tem as próprias faixas
            $groups = ! empty($scale['subscales']) ? $scale['subscales'] : [$scale];

            foreach ($groups as $group) {
                $expected = range(0, $group['max']);

                // Act
                $covered = collect($group['ranges'])->flatMap(fn (array $range) => range($range['min'], $range['max']))->all();

                // Assert
                $this->assertSame($expected, $covered, $scale['slug'].' '.($group['key'] ?? ''));
            }
        }
    }

    public function testItCatalogMaxMatchesItemsAndOptions(): void
    {
        foreach ($this->service->catalog() as $scale) {
            // Arrange
            $highestOption = max(array_column($scale['options'], 'value'));

            if (empty($scale['subscales'])) {
                // Assert: soma simples
                $this->assertSame(count($scale['items']) * $highestOption, $scale['max'], $scale['slug']);

                continue;
            }

            // Assert: cada item pertence a exatamente uma subescala, e o máximo considera o multiplicador
            $indexes = collect($scale['subscales'])->flatMap(fn (array $subscale) => $subscale['items'])->sort()->values()->all();
            $this->assertSame(range(0, count($scale['items']) - 1), $indexes, $scale['slug']);

            foreach ($scale['subscales'] as $subscale) {
                $this->assertSame(count($subscale['items']) * $highestOption * $subscale['multiplier'], $subscale['max'], $subscale['key']);
            }
        }
    }

    public function testItStoreSuccessfullyAndRegistersAccess(): void
    {
        // Arrange
        $this->mockPatientExists();
        $this->encryptionKeys->shouldReceive('existsByUser')->with($this->user->id)->andReturnTrue();
        $created = $this->makeApplication(55, ScaleEnum::PHQ9, '2026-10-18');

        $this->scaleApplications->shouldReceive('create')
            ->once()
            ->with([
                'user_id' => $this->user->id,
                'patient_id' => 10,
                'scale' => 'phq9',
                'applied_on' => '2026-10-18',
                'content_path' => 'scale-applications/abc.bin',
            ])
            ->andReturn($created);

        $this->accessLogService->shouldReceive('registerScaleApplications')
            ->once()
            ->with($this->user->id, AccessLogActionEnum::SCALE_APPLICATION_CREATED, [55]);

        // Act
        $result = $this->service->store($this->storeData(), $this->user->id);

        // Assert
        $this->assertSame($created, $result);
    }

    public function testItStoreThrowExceptionWhenPatientBelongsToAnotherUser(): void
    {
        // Arrange
        $this->mockPatientExists(false);
        $this->scaleApplications->shouldNotReceive('create');

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('scales.patient_not_found'));

        // Act
        $this->service->store($this->storeData(), $this->user->id);
    }

    public function testItStoreThrowExceptionWhenEncryptionKeyDoesNotExist(): void
    {
        // Arrange
        $this->mockPatientExists();
        $this->encryptionKeys->shouldReceive('existsByUser')->andReturnFalse();
        $this->scaleApplications->shouldNotReceive('create');

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('encryption-key.required'));

        // Act
        $this->service->store($this->storeData(), $this->user->id);
    }

    public function testItStoreDoesNotRegisterAccessWhenCreateFails(): void
    {
        // Arrange
        $this->mockPatientExists();
        $this->encryptionKeys->shouldReceive('existsByUser')->andReturnTrue();
        $this->scaleApplications->shouldReceive('create')->andThrow(new \RuntimeException('db down'));
        $this->accessLogService->shouldNotReceive('registerScaleApplications');

        // Assert
        $this->expectException(\RuntimeException::class);

        // Act
        $this->service->store($this->storeData(), $this->user->id);
    }

    public function testItListByPatientReturnsLinksAndRegistersViewed(): void
    {
        // Arrange
        $this->mockPatientExists();
        $this->scaleApplications->shouldReceive('listByPatient')
            ->once()
            ->with(10, $this->user->id, ['id', 'scale', 'applied_on', 'content_path', 'encryption'])
            ->andReturn(new EloquentCollection([
                $this->makeApplication(1, ScaleEnum::PHQ9, now()->subMonth()->toDateString()),
                $this->makeApplication(2, ScaleEnum::GAD7, now()->subYears(6)->toDateString(), ScaleEncryptionEnum::ECDH),
            ]));
        Storage::shouldReceive('temporaryUrl')->twice()->andReturn('https://s3.test/1', 'https://s3.test/2');
        $this->accessLogService->shouldReceive('registerScaleApplications')
            ->once()
            ->with($this->user->id, AccessLogActionEnum::SCALE_APPLICATION_VIEWED, [1, 2]);

        // Act
        $result = $this->service->listByPatient(10, $this->user->id);

        // Assert
        $this->assertCount(2, $result);
        $this->assertSame(1, (int) decrypt($result[0]['id']));
        $this->assertSame('phq9', $result[0]['scale']);
        $this->assertSame('PHQ-9', $result[0]['name']);
        $this->assertSame('https://s3.test/1', $result[0]['link']);
        $this->assertSame('scale-applications/1.bin', $result[0]['content_path']);
        $this->assertSame('symmetric', $result[0]['encryption']);
        $this->assertSame('ecdh', $result[1]['encryption']);
        $this->assertTrue($result[0]['within_retention']);
        $this->assertFalse($result[1]['within_retention']);
        $this->assertArrayNotHasKey('score', $result[0]);
    }

    public function testItListByPatientReturnsEmptyWithoutApplications(): void
    {
        // Arrange
        $this->mockPatientExists();
        $this->scaleApplications->shouldReceive('listByPatient')->andReturn(new EloquentCollection());
        Storage::shouldReceive('temporaryUrl')->never();
        $this->accessLogService->shouldReceive('registerScaleApplications')->once()->with($this->user->id, AccessLogActionEnum::SCALE_APPLICATION_VIEWED, []);

        // Act
        $result = $this->service->listByPatient(10, $this->user->id);

        // Assert
        $this->assertSame([], $result);
    }

    public function testItListByPatientThrowExceptionWhenPatientBelongsToAnotherUser(): void
    {
        // Arrange
        $this->mockPatientExists(false);
        $this->scaleApplications->shouldNotReceive('listByPatient');

        // Assert
        $this->expectException(\DomainException::class);

        // Act
        $this->service->listByPatient(10, $this->user->id);
    }

    public function testItExportByPatientRegistersExportedAction(): void
    {
        // Arrange
        $this->mockPatientExists();
        $this->scaleApplications->shouldReceive('listByPatient')->andReturn(new EloquentCollection([
            $this->makeApplication(3, ScaleEnum::PHQ9, '2026-01-10'),
        ]));
        Storage::shouldReceive('temporaryUrl')->once()->andReturn('https://s3.test/3');
        $this->accessLogService->shouldReceive('registerScaleApplications')
            ->once()
            ->with($this->user->id, AccessLogActionEnum::SCALE_APPLICATION_EXPORTED, [3]);

        // Act
        $result = $this->service->exportByPatient(10, $this->user->id);

        // Assert
        $this->assertSame('10/01/2026', $result[0]['applied_on']);
    }

    public function testItDeleteThrowExceptionWhenWithinRetentionWithoutConfirmation(): void
    {
        // Arrange
        $application = new PatientScaleApplications;
        $application->id = 7;
        $application->applied_on = now()->subYear();
        $this->scaleApplications->shouldReceive('findById')->with(7, $this->user->id, ['id', 'applied_on'])->andReturn($application);

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('scales.retention_confirmation_required'));

        // Act
        $this->scaleApplications->shouldNotReceive('deleteById');
        $this->service->delete(7, $this->user->id);
    }

    public function testItDeleteSuccessfullyWhenRetentionConfirmed(): void
    {
        // Arrange
        $application = new PatientScaleApplications;
        $application->id = 7;
        $application->applied_on = now()->subYear();
        $this->scaleApplications->shouldReceive('findById')->andReturn($application);
        $this->scaleApplications->shouldReceive('deleteById')->once()->with($application->id, $this->user->id)->andReturn(1);
        $this->accessLogService->shouldReceive('registerScaleApplications')
            ->once()
            ->with($this->user->id, AccessLogActionEnum::SCALE_APPLICATION_DELETED, [7]);

        // Act
        $this->service->delete(7, $this->user->id, retentionConfirmed: true);

        // Assert
        $this->addToAssertionCount(1);
    }

    public function testItDeleteWithoutConfirmationWhenOlderThanRetention(): void
    {
        // Arrange
        $application = new PatientScaleApplications;
        $application->id = 8;
        $application->applied_on = now()->subYears(6);
        $this->scaleApplications->shouldReceive('findById')->andReturn($application);
        $this->scaleApplications->shouldReceive('deleteById')->once()->with($application->id, $this->user->id)->andReturn(1);
        $this->accessLogService->shouldReceive('registerScaleApplications')->once();

        // Act
        $this->service->delete(8, $this->user->id);

        // Assert
        $this->addToAssertionCount(1);
    }

    public function testItDeleteThrowExceptionWhenNotFound(): void
    {
        // Arrange
        $this->scaleApplications->shouldReceive('findById')->andReturnNull();

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('scales.not_found'));

        // Act
        $this->service->delete(99, $this->user->id, retentionConfirmed: true);
    }

    public function testItDeleteThrowExceptionAndSkipsAccessLogWhenDeleteFails(): void
    {
        // Arrange
        $application = new PatientScaleApplications;
        $application->id = 9;
        $application->applied_on = now()->subYears(6);
        $this->scaleApplications->shouldReceive('findById')->andReturn($application);
        $this->scaleApplications->shouldReceive('deleteById')->once()->andReturn(0);
        $this->accessLogService->shouldNotReceive('registerScaleApplications');

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('messages.failed_delete'));

        // Act
        $this->service->delete(9, $this->user->id);
    }
}
