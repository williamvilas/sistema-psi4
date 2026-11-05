<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AccessLogActionEnum;
use App\Enums\HealthReceiptBatchStatusEnum;
use App\Enums\HealthReceiptStatusEnum;
use App\Models\HealthReceipt;
use App\Models\HealthReceiptBatch;
use App\Models\Patients;
use App\Models\User;
use App\Services\AccessLogService;
use App\Services\HealthReceiptBatchService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class HealthReceiptBatchServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const PROFESSIONAL_CPF = '52998224725';
    private const PATIENT_CPF = '11144477735';
    private const PAYER_CPF = '39053344705';

    private $healthReceipt;
    private $healthReceiptBatch;
    private $accessLogService;
    private $service;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->healthReceipt = Mockery::mock(HealthReceipt::class);
        $this->healthReceiptBatch = Mockery::mock(HealthReceiptBatch::class);
        $this->accessLogService = Mockery::mock(AccessLogService::class);

        $this->service = new HealthReceiptBatchService(
            $this->healthReceipt,
            $this->healthReceiptBatch,
            $this->accessLogService,
        );

        $this->user = User::factory()->create(['document' => self::PROFESSIONAL_CPF]);
        Auth::login($this->user);
    }

    private function makeReceipt(int $id, int $patientId, ?string $document, ?string $payerDocument = null, string $amount = '150.00'): HealthReceipt
    {
        $patient = new Patients(['document' => $document, 'payer_document' => $payerDocument]);
        $patient->id = $patientId;

        $receipt = new HealthReceipt([
            'patient_id' => $patientId,
            'amount' => $amount,
            'paid_on' => '2026-10-14',
            'status' => HealthReceiptStatusEnum::PENDING->value,
        ]);
        $receipt->id = $id;
        $receipt->setRelation('patient', $patient);

        return $receipt;
    }

    private function makeBatch(int $id, HealthReceiptBatchStatusEnum $status): HealthReceiptBatch
    {
        $batch = new HealthReceiptBatch(['year' => 2026, 'status' => $status->value, 'receipts_count' => 1]);
        $batch->id = $id;

        return $batch;
    }

    public function testItGenerateCsvWithOfficialLayout(): void
    {
        // Arrange
        $receipt = new HealthReceipt([
            'amount' => '1250.5',
            'paid_on' => '2026-10-14',
            'description' => 'Sessão de psicologia',
            'payer_document' => self::PAYER_CPF,
            'beneficiary_document' => self::PATIENT_CPF,
        ]);

        // Act
        $csv = $this->service->buildCsv([$receipt, $receipt], self::PROFESSIONAL_CPF);

        // Assert
        $this->assertFalse(mb_check_encoding($csv, 'UTF-8'), 'O arquivo deve estar em ISO-8859-1, como o modelo da Receita');

        $lines = explode("\r\n", rtrim(mb_convert_encoding($csv, 'UTF-8', 'ISO-8859-1'), "\r\n"));
        $this->assertCount(2, $lines, 'Sem cabeçalho: uma linha por recibo');

        $fields = explode(';', $lines[0]);
        $this->assertCount(16, $fields);
        $this->assertSame([
            '14/10/2026',
            'R01.001.001',
            '255',
            '1250,50',
            '',
            'Sessão de psicologia',
            'PF',
            self::PAYER_CPF,
            self::PATIENT_CPF,
            '',
            '',
            '',
            '',
            'S',
            self::PROFESSIONAL_CPF,
            '',
        ], $fields);
    }

    public function testItSanitizeDescriptionRemovingSemicolonAndLineBreaks(): void
    {
        $this->assertSame('Sessão 1 Sessão 2', $this->service->sanitizeDescription("Sessão 1;\r\nSessão 2 "));
        $this->assertSame(__('health-receipts.default_description'), $this->service->sanitizeDescription(null));
        $this->assertSame(__('health-receipts.default_description'), $this->service->sanitizeDescription(' ; '));
        $this->assertSame(255, mb_strlen($this->service->sanitizeDescription(str_repeat('a', 300))));
    }

    public function testItThrowExceptionWhenUserHasNoDocument(): void
    {
        $user = new User(['document' => null]);

        $this->healthReceipt->shouldNotReceive('lockPendingByPeriod');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('health-receipts.professional_without_document'));

        $this->service->generate(['start_date' => '2026-10-01', 'end_date' => '2026-10-31'], $user);
    }

    public function testItThrowExceptionWhenPeriodCrossesYears(): void
    {
        $this->healthReceipt->shouldNotReceive('lockPendingByPeriod');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('health-receipts.different_years'));

        $this->service->generate(['start_date' => '2025-12-01', 'end_date' => '2026-01-31'], $this->user);
    }

    public function testItThrowExceptionWhenNoReceiptIsReady(): void
    {
        $this->healthReceipt
            ->shouldReceive('lockPendingByPeriod')
            ->once()
            ->andReturn(new EloquentCollection([$this->makeReceipt(1, 10, null)]));

        $this->healthReceiptBatch->shouldNotReceive('create');
        $this->accessLogService->shouldNotReceive('register');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('health-receipts.nothing_to_export'));

        $this->service->generate(['start_date' => '2026-10-01', 'end_date' => '2026-10-31'], $this->user);
    }

    public function testItGenerateSkippingReceiptsWithIssuesAndUsingPayerDocument(): void
    {
        // Arrange: paciente com responsável financeiro e paciente sem CPF
        $withPayer = $this->makeReceipt(1, 10, self::PATIENT_CPF, self::PAYER_CPF);
        $sameWithPayer = $this->makeReceipt(2, 10, self::PATIENT_CPF, self::PAYER_CPF, '100.00');
        $withoutDocument = $this->makeReceipt(3, 11, null);

        $this->healthReceipt
            ->shouldReceive('lockPendingByPeriod')
            ->once()
            ->with($this->user->id, '2026-10-01', '2026-10-31', null)
            ->andReturn(new EloquentCollection([$withPayer, $sameWithPayer, $withoutDocument]));

        $batch = $this->makeBatch(50, HealthReceiptBatchStatusEnum::GENERATED);

        $this->healthReceiptBatch
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $payload) => $payload['receipts_count'] === 2
                && (float) $payload['total_amount'] === 250.0
                && $payload['year'] === 2026
                && $payload['user_id'] === $this->user->id))
            ->andReturn($batch);

        $this->healthReceipt
            ->shouldReceive('updateByIds')
            ->once()
            ->with([1, 2], $this->user->id, [
                'health_receipt_batch_id' => 50,
                'payer_document' => self::PAYER_CPF,
                'beneficiary_document' => self::PATIENT_CPF,
                'status' => HealthReceiptStatusEnum::EXPORTED->value,
            ])
            ->andReturn(2);

        $this->accessLogService
            ->shouldReceive('register')
            ->once()
            ->with($this->user->id, AccessLogActionEnum::HEALTH_RECEIPTS_EXPORTED);

        // Act
        $result = $this->service->generate(['start_date' => '2026-10-01', 'end_date' => '2026-10-31'], $this->user);

        // Assert
        $this->assertCount(1, $result['batches']);
        $this->assertSame(1, $result['skipped']);
    }

    public function testItSplitBatchesWhenMoreThanThousandReceipts(): void
    {
        // Arrange
        $receipts = new EloquentCollection();

        for ($i = 1; $i <= HealthReceiptBatchService::MAX_LINES + 1; $i++) {
            $receipts->push($this->makeReceipt($i, 10, self::PATIENT_CPF));
        }

        $this->healthReceipt->shouldReceive('lockPendingByPeriod')->once()->andReturn($receipts);

        $this->healthReceiptBatch
            ->shouldReceive('create')
            ->twice()
            ->andReturn($this->makeBatch(60, HealthReceiptBatchStatusEnum::GENERATED), $this->makeBatch(61, HealthReceiptBatchStatusEnum::GENERATED));

        $this->healthReceipt->shouldReceive('updateByIds')->twice()->andReturn(1);
        $this->accessLogService->shouldReceive('register')->once();

        // Act
        $result = $this->service->generate(['start_date' => '2026-01-01', 'end_date' => '2026-12-31'], $this->user);

        // Assert
        $this->assertCount(2, $result['batches']);
        $this->assertSame(0, $result['skipped']);
    }

    public function testItDownloadBuildsCsvAndRegistersAccess(): void
    {
        // Arrange
        $receipt = new HealthReceipt([
            'amount' => '150.00',
            'paid_on' => '2026-10-14',
            'payer_document' => self::PATIENT_CPF,
            'beneficiary_document' => self::PATIENT_CPF,
        ]);

        $this->healthReceiptBatch->shouldReceive('findByIdAndUser')->once()->andReturn($this->makeBatch(70, HealthReceiptBatchStatusEnum::IMPORTED));
        $this->healthReceipt->shouldReceive('listByBatch')->once()->andReturn(new EloquentCollection([$receipt]));
        $this->accessLogService
            ->shouldReceive('register')
            ->once()
            ->with($this->user->id, AccessLogActionEnum::HEALTH_RECEIPTS_DOWNLOADED);

        // Act
        $file = $this->service->download(70, $this->user);

        // Assert
        $this->assertSame('receita-saude-2026-arquivo-70.csv', $file['filename']);
        $this->assertTrue($file['imported']);
        $this->assertStringStartsWith('14/10/2026;R01.001.001;255;150,00;;'.__('health-receipts.default_description'), mb_convert_encoding($file['content'], 'UTF-8', 'ISO-8859-1'));
    }

    public function testItThrowExceptionWhenBatchNotFound(): void
    {
        $this->healthReceiptBatch->shouldReceive('findByIdAndUser')->once()->andReturn(null);
        $this->accessLogService->shouldNotReceive('register');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('health-receipts.batch_not_found'));

        $this->service->download(1, $this->user);
    }

    public function testItMarkGeneratedBatchAsImported(): void
    {
        $this->healthReceiptBatch->shouldReceive('findByIdAndUser')->once()->andReturn($this->makeBatch(80, HealthReceiptBatchStatusEnum::GENERATED));
        $this->healthReceiptBatch
            ->shouldReceive('updateByIdAndUser')
            ->once()
            ->with(80, $this->user->id, Mockery::on(fn (array $data) => $data['status'] === HealthReceiptBatchStatusEnum::IMPORTED->value && isset($data['imported_at'])))
            ->andReturn(1);

        $this->service->markImported(80, $this->user->id);

        $this->addToAssertionCount(1);
    }

    public function testItThrowExceptionWhenMarkingUndoneBatchAsImported(): void
    {
        $this->healthReceiptBatch->shouldReceive('findByIdAndUser')->once()->andReturn($this->makeBatch(81, HealthReceiptBatchStatusEnum::UNDONE));
        $this->healthReceiptBatch->shouldNotReceive('updateByIdAndUser');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('health-receipts.cannot_mark_imported'));

        $this->service->markImported(81, $this->user->id);
    }

    public function testItUndoBatchReturnsReceiptsToPendingAndKeepsRefundedCancelled(): void
    {
        // Arrange
        $this->healthReceiptBatch->shouldReceive('findByIdAndUser')->once()->andReturn($this->makeBatch(90, HealthReceiptBatchStatusEnum::IMPORTED));

        $this->healthReceipt
            ->shouldReceive('updateByBatch')
            ->once()
            ->with(90, $this->user->id, Mockery::on(fn (array $data) => $data['status'] === HealthReceiptStatusEnum::PENDING->value
                && $data['health_receipt_batch_id'] === null
                && $data['payer_document'] === null), false)
            ->andReturn(3);

        $this->healthReceipt
            ->shouldReceive('updateByBatch')
            ->once()
            ->with(90, $this->user->id, Mockery::on(fn (array $data) => $data['status'] === HealthReceiptStatusEnum::CANCELLED->value), true)
            ->andReturn(1);

        $this->healthReceiptBatch
            ->shouldReceive('updateByIdAndUser')
            ->once()
            ->with(90, $this->user->id, Mockery::on(fn (array $data) => $data['status'] === HealthReceiptBatchStatusEnum::UNDONE->value))
            ->andReturn(1);

        // Act
        $this->service->undo(90, $this->user->id);

        // Assert
        $this->addToAssertionCount(1);
    }

    public function testItThrowExceptionWhenUndoingUndoneBatch(): void
    {
        $this->healthReceiptBatch->shouldReceive('findByIdAndUser')->once()->andReturn($this->makeBatch(91, HealthReceiptBatchStatusEnum::UNDONE));
        $this->healthReceipt->shouldNotReceive('updateByBatch');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('health-receipts.cannot_undo'));

        $this->service->undo(91, $this->user->id);
    }
}
