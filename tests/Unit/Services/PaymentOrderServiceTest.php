<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\PaymentAccountsStatusEnum;
use App\Enums\PaymentOrderIntegrationStatusEnum;
use App\Enums\PaymentOrderStatusEnum;
use App\Jobs\CreateMercadoPagoChargeJob;
use App\Models\Patients;
use App\Models\PaymentAccount;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Services\HealthReceiptService;
use App\Services\PaymentAccountService;
use App\Services\PaymentOrderService;
use App\Wrappers\MercadoPagoConnectWrapper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class PaymentOrderServiceTest extends TestCase
{
    use DatabaseTransactions;

    private $paymentOrder;
    private $patients;
    private $paymentAccount;
    private $mercadoPagoConnectWrapper;
    private $service;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentOrder = Mockery::mock(PaymentOrder::class);
        $this->patients = Mockery::mock(Patients::class);
        $this->paymentAccount = Mockery::mock(PaymentAccount::class);
        $this->mercadoPagoConnectWrapper = Mockery::mock(MercadoPagoConnectWrapper::class);

        $this->service = new PaymentOrderService(
            $this->paymentOrder,
            $this->patients,
            $this->paymentAccount,
            $this->mercadoPagoConnectWrapper,
            app(PaymentAccountService::class),
            Mockery::mock(HealthReceiptService::class),
        );

        $this->user = User::factory()->create();
    }

    public function testItStoreSuccessfullyWhenAccountConnected(): void
    {
        Bus::fake();

        $data = [
            'patient_id' => 5,
            'amount' => 150.0,
            'description' => 'Consulta',
            'payment_methods' => ['pix', 'card'],
        ];

        $this->mockPatientFound($data['patient_id']);

        $this->paymentAccount
            ->shouldReceive('findByUserAndProvider')
            ->once()
            ->andReturn(new PaymentAccount(['status' => PaymentAccountsStatusEnum::INTEGRATED->value]));

        $order = new PaymentOrder();
        $order->id = 99;
        $order->exists = true;

        $this->paymentOrder
            ->shouldReceive('create')
            ->once()
            ->andReturn($order);

        $result = $this->service->store($data, $this->user->id);

        $this->assertInstanceOf(PaymentOrder::class, $result);
        Bus::assertDispatched(CreateMercadoPagoChargeJob::class, fn ($job) => $job->paymentOrderId === 99);
    }

    public function testItThrowExceptionWhenPatientNotFound(): void
    {
        $this->patients->shouldReceive('newQuery')->andReturnSelf();
        $this->patients->shouldReceive('select')->andReturnSelf();
        $this->patients->shouldReceive('where')->andReturnSelf();
        $this->patients->shouldReceive('first')->andReturn(null);

        $this->expectException(\DomainException::class);

        $this->service->store(['patient_id' => 5, 'amount' => 10, 'payment_methods' => ['pix']], $this->user->id);
    }

    public function testItThrowExceptionWhenAccountNotConnected(): void
    {
        $this->mockPatientFound(5);

        $this->paymentAccount
            ->shouldReceive('findByUserAndProvider')
            ->once()
            ->andReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('payments.account_not_connected'));

        $this->service->store(['patient_id' => 5, 'amount' => 10, 'payment_methods' => ['pix']], $this->user->id);
    }

    public function testItThrowExceptionWhenAccountNeedsReauth(): void
    {
        $this->mockPatientFound(5);

        $this->paymentAccount
            ->shouldReceive('findByUserAndProvider')
            ->once()
            ->andReturn(new PaymentAccount(['status' => PaymentAccountsStatusEnum::NEEDS_REAUTH->value]));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('payments.account_needs_reauth'));

        $this->service->store(['patient_id' => 5, 'amount' => 10, 'payment_methods' => ['pix']], $this->user->id);
    }

    public function testItThrowExceptionWhenDeleteFails(): void
    {
        $this->expectException(\DomainException::class);

        $this->service->delete([], $this->user->id);
    }

    public function testItPaginateWithPatientFilter(): void
    {
        $paginator = Mockery::mock(LengthAwarePaginator::class);
        $paginator->shouldReceive('withQueryString')->once()->andReturnSelf();

        $this->paymentOrder->shouldReceive('paginateByUserWithPatient')
            ->once()
            ->withArgs(fn (...$args) => $args[0] === $this->user->id
                && $args[2] === null
                && $args[3] === 7
                && $args[5] === 10)
            ->andReturn($paginator);

        $result = $this->service->paginate([], $this->user->id, 10, 7);

        $this->assertSame($paginator, $result);
    }

    public function testItPaginateWithoutPatientFilter(): void
    {
        $paginator = Mockery::mock(LengthAwarePaginator::class);
        $paginator->shouldReceive('withQueryString')->once()->andReturnSelf();

        $this->paymentOrder->shouldReceive('paginateByUserWithPatient')
            ->once()
            ->withArgs(fn (...$args) => $args[2] === null && $args[3] === null)
            ->andReturn($paginator);

        $this->assertSame($paginator, $this->service->paginate([], $this->user->id, 10));
    }

    public function testItPaginateWithStatusAndPatientFilters(): void
    {
        $paginator = Mockery::mock(LengthAwarePaginator::class);
        $paginator->shouldReceive('withQueryString')->once()->andReturnSelf();

        $this->paymentOrder->shouldReceive('paginateByUserWithPatient')
            ->once()
            ->withArgs(fn (...$args) => $args[2] === 'paid' && $args[3] === 3)
            ->andReturn($paginator);

        $this->assertSame($paginator, $this->service->paginate(['status' => 'paid'], $this->user->id, 10, 3));
    }

    public function testItSummaryReturnsAllIndicators(): void
    {
        $timezone = 'America/Sao_Paulo';
        $today = now($timezone)->toDateString();

        $this->paymentOrder->shouldReceive('summarizePending')
            ->once()
            ->with($this->user->id, $today)
            ->andReturn(['count' => 2, 'total' => 300.0]);

        $this->paymentOrder->shouldReceive('summarizeByStatusAndPeriod')
            ->once()
            ->withArgs(fn ($userId, $status, $start, $end) => $userId === $this->user->id
                && $status === PaymentOrderStatusEnum::PAID
                && $start->equalTo(now($timezone)->startOfMonth())
                && $end->greaterThanOrEqualTo($start))
            ->andReturn(['count' => 1, 'total' => 150.0]);

        $this->paymentOrder->shouldReceive('summarizeOverdue')
            ->once()
            ->with($this->user->id, $today)
            ->andReturn(['count' => 3, 'total' => 450.5]);

        $result = $this->service->summary($this->user->id, $timezone);

        $this->assertSame([
            'receivable' => ['count' => 2, 'total' => 300.0],
            'received_month' => ['count' => 1, 'total' => 150.0],
            'overdue' => ['count' => 3, 'total' => 450.5],
        ], $result);
    }

    public function testItSummaryReturnsZerosWhenUserHasNoOrders(): void
    {
        $empty = ['count' => 0, 'total' => 0.0];

        $this->paymentOrder->shouldReceive('summarizePending')->once()->andReturn($empty);
        $this->paymentOrder->shouldReceive('summarizeByStatusAndPeriod')->once()->andReturn($empty);
        $this->paymentOrder->shouldReceive('summarizeOverdue')->once()->andReturn($empty);

        $result = $this->service->summary($this->user->id, 'America/Sao_Paulo');

        $this->assertSame(['receivable' => $empty, 'received_month' => $empty, 'overdue' => $empty], $result);
    }

    public function testItSummaryUsesCacheOnSecondCall(): void
    {
        $data = ['count' => 1, 'total' => 10.0];

        $this->paymentOrder->shouldReceive('summarizePending')->once()->andReturn($data);
        $this->paymentOrder->shouldReceive('summarizeByStatusAndPeriod')->once()->andReturn($data);
        $this->paymentOrder->shouldReceive('summarizeOverdue')->once()->andReturn($data);

        $first = $this->service->summary($this->user->id, 'America/Sao_Paulo');
        $second = $this->service->summary($this->user->id, 'America/Sao_Paulo');

        $this->assertSame($first, $second);
    }

    public function testItThrowExceptionWhenUpdateOrderIsNotPending(): void
    {
        $this->mockOrderForUpdate(PaymentOrderStatusEnum::PAID, PaymentOrderIntegrationStatusEnum::INTEGRATED);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('payments.cannot_edit'));

        $this->service->update(1, ['amount' => 10, 'payment_methods' => ['pix']], $this->user->id);
    }

    public function testItThrowExceptionWhenUpdatePendingOrderWithCheckoutProcessing(): void
    {
        $this->mockOrderForUpdate(PaymentOrderStatusEnum::PENDING, PaymentOrderIntegrationStatusEnum::PROCESSING);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('payments.cannot_edit'));

        $this->service->update(1, ['amount' => 10, 'payment_methods' => ['pix']], $this->user->id);
    }

    public function testItThrowExceptionWhenUpdateAccountNotConnected(): void
    {
        $this->mockOrderForUpdate(PaymentOrderStatusEnum::PENDING, PaymentOrderIntegrationStatusEnum::PENDING);
        $this->mockAccount(null);

        $this->paymentOrder->shouldNotReceive('updateByIdAndUser');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('payments.account_not_connected'));

        $this->service->update(1, ['amount' => 10, 'payment_methods' => ['pix']], $this->user->id);
    }

    public function testItThrowExceptionWhenUpdateAccountNeedsReauth(): void
    {
        $this->mockOrderForUpdate(PaymentOrderStatusEnum::PENDING, PaymentOrderIntegrationStatusEnum::ERROR);
        $this->mockAccount(PaymentAccountsStatusEnum::NEEDS_REAUTH);

        $this->paymentOrder->shouldNotReceive('updateByIdAndUser');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('payments.account_needs_reauth'));

        $this->service->update(1, ['amount' => 10, 'payment_methods' => ['pix']], $this->user->id);
    }

    public function testItUpdateSuccessfullyWhenOrderIsEditable(): void
    {
        Bus::fake();

        $this->mockOrderForUpdate(PaymentOrderStatusEnum::PENDING, PaymentOrderIntegrationStatusEnum::PENDING);
        $this->mockAccount(PaymentAccountsStatusEnum::INTEGRATED);

        $this->mercadoPagoConnectWrapper->shouldNotReceive('updatePreference');

        $this->paymentOrder->shouldReceive('updateByIdAndUser')
            ->once()
            ->withArgs(fn ($id, $userId, $data) => $id === 1
                && $userId === $this->user->id
                && $data['amount'] === 10
                && $data['description'] === null
                && $data['due_date'] === null
                && $data['payment_methods'] === '["pix"]'
                && ! array_key_exists('integration_status', $data))
            ->andReturn(1);

        $this->service->update(1, ['amount' => 10, 'payment_methods' => ['pix']], $this->user->id);

        Bus::assertNotDispatched(CreateMercadoPagoChargeJob::class);
    }

    public function testItUpdateAndRequeueChargeWhenIntegrationFailed(): void
    {
        Bus::fake();

        $this->mockOrderForUpdate(PaymentOrderStatusEnum::PENDING, PaymentOrderIntegrationStatusEnum::ERROR);
        $this->mockAccount(PaymentAccountsStatusEnum::INTEGRATED);

        $this->mercadoPagoConnectWrapper->shouldNotReceive('updatePreference');

        $this->paymentOrder->shouldReceive('updateByIdAndUser')
            ->once()
            ->withArgs(fn ($id, $userId, $data) => $data['description'] === 'Sessões de outubro'
                && $data['due_date'] === '2026-10-30'
                && $data['payment_methods'] === '["pix","card"]'
                && $data['integration_status'] === PaymentOrderIntegrationStatusEnum::PENDING->value)
            ->andReturn(1);

        $this->service->update(1, [
            'amount' => 250,
            'description' => 'Sessões de outubro',
            'due_date' => '2026-10-30',
            'payment_methods' => ['pix', 'card'],
        ], $this->user->id);

        Bus::assertDispatched(CreateMercadoPagoChargeJob::class, fn ($job) => $job->paymentOrderId === 1);
    }

    public function testItUpdateIntegratedOrderAndSyncPreferenceInMercadoPago(): void
    {
        Bus::fake();

        $dueDate = now('America/Sao_Paulo')->addDays(5)->toDateString();

        $this->mockOrderForUpdate(PaymentOrderStatusEnum::PENDING, PaymentOrderIntegrationStatusEnum::INTEGRATED);
        $this->mockAccount(PaymentAccountsStatusEnum::INTEGRATED);
        $this->mockSellerAccessToken();

        $this->paymentOrder->shouldReceive('updateByIdAndUser')
            ->once()
            ->withArgs(fn ($id, $userId, $data) => $data['amount'] === 300
                && ! array_key_exists('integration_status', $data))
            ->andReturn(1);

        $this->mercadoPagoConnectWrapper->shouldReceive('updatePreference')
            ->once()
            ->withArgs(fn ($token, $preferenceId, $body) => $token === 'seller-token'
                && $preferenceId === 'pref-123'
                && $body['items'][0]['unit_price'] === 300.0
                && $body['items'][0]['description'] === 'Sessões de novembro'
                && $body['external_reference'] === 'uuid-1'
                && $body['payer']['email'] === 'paciente@example.com'
                && $body['payment_methods']['excluded_payment_types'] !== []
                && $body['expires'] === true
                && str_starts_with($body['expiration_date_to'], $dueDate.'T23:59:59.999'))
            ->andReturnNull();

        $this->service->update(1, [
            'amount' => 300,
            'description' => 'Sessões de novembro',
            'due_date' => $dueDate,
            'payment_methods' => ['pix'],
        ], $this->user->id);

        Bus::assertNotDispatched(CreateMercadoPagoChargeJob::class);
    }

    public function testItRemoveExpirationWhenIntegratedOrderHasNoDueDate(): void
    {
        $this->mockOrderForUpdate(PaymentOrderStatusEnum::PENDING, PaymentOrderIntegrationStatusEnum::INTEGRATED);
        $this->mockAccount(PaymentAccountsStatusEnum::INTEGRATED);
        $this->mockSellerAccessToken();

        $this->paymentOrder->shouldReceive('updateByIdAndUser')->once()->andReturn(1);

        $this->mercadoPagoConnectWrapper->shouldReceive('updatePreference')
            ->once()
            ->withArgs(fn ($token, $preferenceId, $body) => $body['expires'] === false
                && ! array_key_exists('expiration_date_to', $body))
            ->andReturnNull();

        $this->service->update(1, ['amount' => 10, 'payment_methods' => ['pix']], $this->user->id);
    }

    public function testItThrowExceptionWhenMercadoPagoRejectsIntegratedOrderUpdate(): void
    {
        $this->mockOrderForUpdate(PaymentOrderStatusEnum::PENDING, PaymentOrderIntegrationStatusEnum::INTEGRATED);
        $this->mockAccount(PaymentAccountsStatusEnum::INTEGRATED);
        $this->mockSellerAccessToken();

        $this->paymentOrder->shouldReceive('updateByIdAndUser')->once()->andReturn(1);

        $this->mercadoPagoConnectWrapper->shouldReceive('updatePreference')
            ->once()
            ->andThrow(new \DomainException(__('payments.order_update_rejected')));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('payments.order_update_rejected'));

        $this->service->update(1, ['amount' => 10, 'payment_methods' => ['pix']], $this->user->id);
    }

    public function testItThrowExceptionWhenMercadoPagoUnavailableOnIntegratedOrderUpdate(): void
    {
        $this->mockOrderForUpdate(PaymentOrderStatusEnum::PENDING, PaymentOrderIntegrationStatusEnum::INTEGRATED);
        $this->mockAccount(PaymentAccountsStatusEnum::INTEGRATED);
        $this->mockSellerAccessToken();

        $this->paymentOrder->shouldReceive('updateByIdAndUser')->once()->andReturn(1);

        $this->mercadoPagoConnectWrapper->shouldReceive('updatePreference')
            ->once()
            ->andThrow(new \RuntimeException('connection refused'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('messages.failed_update'));

        $this->service->update(1, ['amount' => 10, 'payment_methods' => ['pix']], $this->user->id);
    }

    public function testItThrowExceptionWhenUpdateIntegratedOrderWithoutSellerToken(): void
    {
        $this->mockOrderForUpdate(PaymentOrderStatusEnum::PENDING, PaymentOrderIntegrationStatusEnum::INTEGRATED);
        $this->mockAccount(PaymentAccountsStatusEnum::INTEGRATED);

        $this->paymentAccount->shouldReceive('findIntegratedByUser')->once()->andReturn(null);
        $this->paymentOrder->shouldNotReceive('updateByIdAndUser');
        $this->mercadoPagoConnectWrapper->shouldNotReceive('updatePreference');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('payments.account_needs_reauth'));

        $this->service->update(1, ['amount' => 10, 'payment_methods' => ['pix']], $this->user->id);
    }

    public function testItThrowExceptionWhenUpdateFails(): void
    {
        $this->mockOrderForUpdate(PaymentOrderStatusEnum::PENDING, PaymentOrderIntegrationStatusEnum::PENDING);
        $this->mockAccount(PaymentAccountsStatusEnum::INTEGRATED);

        $this->paymentOrder->shouldReceive('updateByIdAndUser')->once()->andThrow(new \RuntimeException('db down'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('messages.failed_update'));

        $this->service->update(1, ['amount' => 10, 'payment_methods' => ['pix']], $this->user->id);
    }

    private function mockAccount(?PaymentAccountsStatusEnum $status): void
    {
        $this->paymentAccount
            ->shouldReceive('findByUserAndProvider')
            ->once()
            ->andReturn($status ? new PaymentAccount(['status' => $status->value]) : null);
    }

    private function mockSellerAccessToken(): void
    {
        // Token ainda válido: o PaymentAccountService devolve sem renovar no Mercado Pago
        $account = new PaymentAccount();
        $account->access_token = 'seller-token';
        $account->token_expires_at = now()->addDay();

        $this->paymentAccount->shouldReceive('findIntegratedByUser')->once()->andReturn($account);
    }

    private function mockOrderForUpdate(PaymentOrderStatusEnum $status, PaymentOrderIntegrationStatusEnum $integrationStatus): void
    {
        $patient = new Patients();
        $patient->email = 'paciente@example.com';

        $order = new PaymentOrder();
        $order->id = 1;
        $order->uuid = 'uuid-1';
        $order->user_id = $this->user->id;
        $order->external_id = 'pref-123';
        $order->status = $status;
        $order->integration_status = $integrationStatus;
        $order->setRelation('patient', $patient);

        $this->paymentOrder->shouldReceive('findByIdAndUserWithPatient')->once()->andReturn($order);
    }

    private function mockPatientFound(int $patientId): void
    {
        $this->patients->shouldReceive('newQuery')->andReturnSelf();
        $this->patients->shouldReceive('select')->andReturnSelf();
        $this->patients->shouldReceive('where')->andReturnSelf();
        $this->patients->shouldReceive('first')->andReturn((object) ['id' => $patientId]);
    }

    public function testItBuildPreferenceWithMarketplaceFeeByPlan(): void
    {
        $order = new PaymentOrder(['amount' => 200, 'payment_methods' => ['pix']]);
        $order->uuid = 'uuid-fee';

        $this->user->current_plan = 'free';
        $order->setRelation('user', $this->user);
        $this->assertSame(4.0, $this->service->buildPreferenceBody($order)['marketplace_fee']);

        $this->user->current_plan = 'professional';
        $this->assertArrayNotHasKey('marketplace_fee', $this->service->buildPreferenceBody($order));
    }

    public function testItBuildPreferenceWithoutMarketplaceFeeWhenPlanHasNoFee(): void
    {
        config(['plans.plans.professional.payment_fee_percent' => 0]);
        $order = new PaymentOrder(['amount' => 200, 'payment_methods' => ['pix']]);
        $order->uuid = 'uuid-fee';
        $this->user->current_plan = 'professional';
        $order->setRelation('user', $this->user);

        $this->assertArrayNotHasKey('marketplace_fee', $this->service->buildPreferenceBody($order));
    }
}
