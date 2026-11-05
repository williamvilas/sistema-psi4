<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\HealthReceiptSourceEnum;
use App\Enums\HealthReceiptStatusEnum;
use App\Enums\PaymentOrderStatusEnum;
use App\Models\HealthReceipt;
use App\Models\Patients;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Services\HealthReceiptService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class HealthReceiptServiceTest extends TestCase
{
    use DatabaseTransactions;

    private $healthReceipt;
    private $patients;
    private $paymentOrder;
    private $userModel;
    private $service;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->healthReceipt = Mockery::mock(HealthReceipt::class);
        $this->patients = Mockery::mock(Patients::class);
        $this->paymentOrder = Mockery::mock(PaymentOrder::class);
        $this->userModel = Mockery::mock(User::class);

        $this->service = new HealthReceiptService(
            $this->healthReceipt,
            $this->patients,
            $this->paymentOrder,
            $this->userModel,
        );

        $this->user = User::factory()->create(['document' => '52998224725']);
        Auth::login($this->user);
    }

    private function makeReceipt(array $attributes): HealthReceipt
    {
        $receipt = new HealthReceipt($attributes);
        $receipt->id = $attributes['id'] ?? 1;
        $receipt->exists = true;

        return $receipt;
    }

    public function testItStoreManualReceiptSuccessfully(): void
    {
        // Arrange
        $data = ['patient_id' => 5, 'amount' => '150.00', 'paid_on' => '2026-10-10', 'description' => null];

        $this->patients->shouldReceive('findById')->once()->with(5, $this->user->id, ['id'])->andReturn(new Patients());

        $this->healthReceipt
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $payload) => $payload['user_id'] === $this->user->id
                && $payload['patient_id'] === 5
                && $payload['source'] === HealthReceiptSourceEnum::MANUAL->value
                && $payload['status'] === HealthReceiptStatusEnum::PENDING->value
                && $payload['paid_on'] === '2026-10-10'
                && ! empty($payload['uuid'])))
            ->andReturn(new HealthReceipt());

        // Act
        $receipt = $this->service->store($data, $this->user->id);

        // Assert
        $this->assertInstanceOf(HealthReceipt::class, $receipt);
    }

    public function testItThrowExceptionWhenPatientNotFromUser(): void
    {
        $this->patients->shouldReceive('findById')->once()->andReturn(null);
        $this->healthReceipt->shouldNotReceive('create');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('health-receipts.patient_not_found'));

        $this->service->store(['patient_id' => 99, 'amount' => '10', 'paid_on' => '2026-10-10'], $this->user->id);
    }

    public function testItUpdateManualPendingReceiptSuccessfully(): void
    {
        // Arrange
        $receipt = $this->makeReceipt(['source' => HealthReceiptSourceEnum::MANUAL->value, 'status' => HealthReceiptStatusEnum::PENDING->value]);
        $data = ['patient_id' => 5, 'amount' => '200.00', 'paid_on' => '2026-10-11', 'description' => 'Sessões de outubro'];

        $this->healthReceipt->shouldReceive('findByIdAndUser')->once()->andReturn($receipt);
        $this->patients->shouldReceive('findById')->once()->andReturn(new Patients());
        $this->healthReceipt
            ->shouldReceive('updateByIdAndUser')
            ->once()
            ->with(1, $this->user->id, $data)
            ->andReturn(1);

        // Act
        $this->service->update(1, $data, $this->user->id);

        // Assert
        $this->addToAssertionCount(1);
    }

    public function testItThrowExceptionWhenUpdatingReceiptFromPaymentOrder(): void
    {
        $receipt = $this->makeReceipt(['source' => HealthReceiptSourceEnum::PAYMENT_ORDER->value, 'status' => HealthReceiptStatusEnum::PENDING->value]);

        $this->healthReceipt->shouldReceive('findByIdAndUser')->once()->andReturn($receipt);
        $this->healthReceipt->shouldNotReceive('updateByIdAndUser');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('health-receipts.cannot_edit'));

        $this->service->update(1, ['patient_id' => 5, 'amount' => '1', 'paid_on' => '2026-10-11'], $this->user->id);
    }

    public function testItThrowExceptionWhenReceiptNotFound(): void
    {
        $this->healthReceipt->shouldReceive('findByIdAndUser')->once()->andReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('health-receipts.not_found'));

        $this->service->skip(1, $this->user->id);
    }

    public function testItThrowExceptionWhenDeletingExportedReceipt(): void
    {
        $receipt = $this->makeReceipt(['source' => HealthReceiptSourceEnum::MANUAL->value, 'status' => HealthReceiptStatusEnum::EXPORTED->value]);

        $this->healthReceipt->shouldReceive('findByIdAndUser')->once()->andReturn($receipt);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('health-receipts.cannot_delete'));

        $this->service->delete(1, $this->user->id);
    }

    public function testItSkipPendingReceipt(): void
    {
        $receipt = $this->makeReceipt(['status' => HealthReceiptStatusEnum::PENDING->value]);

        $this->healthReceipt->shouldReceive('findByIdAndUser')->once()->andReturn($receipt);
        $this->healthReceipt
            ->shouldReceive('updateByIdAndUser')
            ->once()
            ->with(1, $this->user->id, ['status' => HealthReceiptStatusEnum::CANCELLED->value])
            ->andReturn(1);

        $this->service->skip(1, $this->user->id);

        $this->addToAssertionCount(1);
    }

    public function testItThrowExceptionWhenSkippingExportedReceipt(): void
    {
        $receipt = $this->makeReceipt(['status' => HealthReceiptStatusEnum::EXPORTED->value]);

        $this->healthReceipt->shouldReceive('findByIdAndUser')->once()->andReturn($receipt);
        $this->healthReceipt->shouldNotReceive('updateByIdAndUser');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('health-receipts.cannot_skip'));

        $this->service->skip(1, $this->user->id);
    }

    public function testItRestoreCancelledReceipt(): void
    {
        $receipt = $this->makeReceipt(['status' => HealthReceiptStatusEnum::CANCELLED->value]);

        $this->healthReceipt->shouldReceive('findByIdAndUser')->once()->andReturn($receipt);
        $this->healthReceipt
            ->shouldReceive('updateByIdAndUser')
            ->once()
            ->with(1, $this->user->id, ['status' => HealthReceiptStatusEnum::PENDING->value])
            ->andReturn(1);

        $this->service->restore(1, $this->user->id);

        $this->addToAssertionCount(1);
    }

    public function testItThrowExceptionWhenRestoringRefundedReceipt(): void
    {
        $receipt = $this->makeReceipt(['status' => HealthReceiptStatusEnum::CANCELLED->value, 'refunded_at' => now()]);

        $this->healthReceipt->shouldReceive('findByIdAndUser')->once()->andReturn($receipt);
        $this->healthReceipt->shouldNotReceive('updateByIdAndUser');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('health-receipts.cannot_restore'));

        $this->service->restore(1, $this->user->id);
    }

    public function testItCreateFromPaymentOrderInUserTimezone(): void
    {
        // Arrange: Pix pago às 22h30 de 31/12 em Brasília é 01h30 de 01/01 em UTC
        $order = new PaymentOrder([
            'user_id' => $this->user->id,
            'patient_id' => 7,
            'amount' => '180.00',
            'description' => 'Sessão',
            'status' => PaymentOrderStatusEnum::PAID->value,
            'paid_at' => Carbon::parse('2026-01-01 01:30:00', 'UTC'),
        ]);
        $order->id = 30;

        $this->paymentOrder->shouldReceive('findById')->once()->with(30, Mockery::any())->andReturn($order);
        $this->healthReceipt->shouldReceive('findByPaymentOrder')->once()->with(30, ['id'])->andReturn(null);
        $this->userModel->shouldReceive('findById')->once()->with($this->user->id)->andReturn(new User(['timezone' => 'America/Sao_Paulo']));

        $this->healthReceipt
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $payload) => $payload['paid_on'] === '2025-12-31'
                && $payload['payment_order_id'] === 30
                && $payload['patient_id'] === 7
                && $payload['source'] === HealthReceiptSourceEnum::PAYMENT_ORDER->value))
            ->andReturn(new HealthReceipt());

        // Act
        $this->service->createFromPaymentOrder(30);

        // Assert
        $this->addToAssertionCount(1);
    }

    public function testItCreateFromPaymentOrderOnlyOnce(): void
    {
        $order = new PaymentOrder(['status' => PaymentOrderStatusEnum::PAID->value, 'paid_at' => now()]);
        $order->id = 31;

        $this->paymentOrder->shouldReceive('findById')->once()->andReturn($order);
        $this->healthReceipt->shouldReceive('findByPaymentOrder')->once()->andReturn(new HealthReceipt());
        $this->healthReceipt->shouldNotReceive('create');

        $this->service->createFromPaymentOrder(31);

        $this->addToAssertionCount(1);
    }

    public function testItIgnoreOrderThatIsNotPaid(): void
    {
        $order = new PaymentOrder(['status' => PaymentOrderStatusEnum::PENDING->value]);
        $order->id = 32;

        $this->paymentOrder->shouldReceive('findById')->once()->andReturn($order);
        $this->healthReceipt->shouldNotReceive('findByPaymentOrder');
        $this->healthReceipt->shouldNotReceive('create');

        $this->service->createFromPaymentOrder(32);

        $this->addToAssertionCount(1);
    }

    public function testItCancelPendingReceiptWhenRefunded(): void
    {
        $receipt = $this->makeReceipt(['id' => 4, 'user_id' => $this->user->id, 'status' => HealthReceiptStatusEnum::PENDING->value]);

        $this->healthReceipt->shouldReceive('findByPaymentOrder')->once()->with(40, Mockery::any())->andReturn($receipt);
        $this->healthReceipt
            ->shouldReceive('updateByIdAndUser')
            ->once()
            ->with(4, $this->user->id, Mockery::on(fn (array $data) => $data['status'] === HealthReceiptStatusEnum::CANCELLED->value && isset($data['refunded_at'])))
            ->andReturn(1);

        $this->service->handleRefund(40);

        $this->addToAssertionCount(1);
    }

    public function testItFlagExportedReceiptWhenRefunded(): void
    {
        $receipt = $this->makeReceipt(['id' => 5, 'user_id' => $this->user->id, 'status' => HealthReceiptStatusEnum::EXPORTED->value]);

        $this->healthReceipt->shouldReceive('findByPaymentOrder')->once()->andReturn($receipt);
        $this->healthReceipt
            ->shouldReceive('updateByIdAndUser')
            ->once()
            ->with(5, $this->user->id, Mockery::on(fn (array $data) => ! isset($data['status']) && isset($data['refunded_at'])))
            ->andReturn(1);

        $this->service->handleRefund(41);

        $this->addToAssertionCount(1);
    }

    public function testItIgnoreRefundWithoutReceipt(): void
    {
        $this->healthReceipt->shouldReceive('findByPaymentOrder')->once()->andReturn(null);
        $this->healthReceipt->shouldNotReceive('updateByIdAndUser');

        $this->service->handleRefund(42);

        $this->addToAssertionCount(1);
    }

    public function testItReturnLastOverdueDateConsideringFebruaryDeadline(): void
    {
        // Em fevereiro de 2026 ainda dá para emitir os recibos de 2025
        $this->assertSame('2024-12-31', $this->service->lastOverdueDate(Carbon::create(2026, 2, 28)));
        // A partir de março, os recibos de 2025 estão fora do prazo
        $this->assertSame('2025-12-31', $this->service->lastOverdueDate(Carbon::create(2026, 3, 1)));
    }

    public function testItReturnOverviewWithMissingProfessionalDocument(): void
    {
        // Arrange
        $user = new User(['document' => null]);
        $user->id = $this->user->id;

        $this->healthReceipt->shouldReceive('summarizePending')->once()->with($this->user->id)->andReturn(['count' => 2, 'total' => 300.0]);
        $this->healthReceipt->shouldReceive('countPendingPaidUntil')->once()->andReturn(0);
        $this->healthReceipt->shouldReceive('listRefundedExported')->once()->with($this->user->id, 5)->andReturn(new EloquentCollection());

        // Act
        $overview = $this->service->overview($user, 'America/Sao_Paulo');

        // Assert
        $this->assertTrue($overview['missing_professional_document']);
        $this->assertSame(2, $overview['pending']['count']);
        $this->assertSame(0, $overview['overdue_count']);
        $this->assertTrue($overview['refunded']->isEmpty());
    }

    public function testItPaginateWithAllFilters(): void
    {
        // Arrange
        $paginator = new LengthAwarePaginator([], 0, 10);

        $this->healthReceipt
            ->shouldReceive('paginateByUserWithPatient')
            ->once()
            ->with(
                $this->user->id,
                Mockery::type('array'),
                HealthReceiptStatusEnum::PENDING->value,
                9,
                '2026-10-01',
                '2026-10-31',
                10,
            )
            ->andReturn($paginator);

        // Act
        $result = $this->service->paginate(
            ['status' => HealthReceiptStatusEnum::PENDING->value, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31'],
            $this->user->id,
            10,
            9,
        );

        // Assert
        $this->assertSame($paginator, $result);
    }
}
