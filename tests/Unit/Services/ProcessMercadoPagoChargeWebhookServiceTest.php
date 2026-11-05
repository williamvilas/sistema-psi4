<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentOrderStatusEnum;
use App\Models\PaymentOrder;
use App\Models\Webhook;
use App\Services\HealthReceiptService;
use App\Services\ProcessMercadoPagoChargeWebhookService;
use App\Wrappers\MercadoPagoWrapper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class ProcessMercadoPagoChargeWebhookServiceTest extends TestCase
{
    use DatabaseTransactions;

    private $paymentOrder;
    private $mercadoPagoWrapper;
    private $healthReceiptService;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentOrder = Mockery::mock(PaymentOrder::class);
        $this->mercadoPagoWrapper = Mockery::mock(MercadoPagoWrapper::class);

        $this->healthReceiptService = Mockery::mock(HealthReceiptService::class);

        $this->service = new ProcessMercadoPagoChargeWebhookService($this->paymentOrder, $this->mercadoPagoWrapper, $this->healthReceiptService);
    }

    public function testItThrowExceptionWhenDataIdMissing(): void
    {
        $webhook = new Webhook(['payload' => ['type' => 'payment', 'data' => []]]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('payments.webhook_missing_data_id'));

        $this->service->handle($webhook);
    }

    public function testItUpdatesOrderToPaidWithCardPayment(): void
    {
        $order = new PaymentOrder(['status' => PaymentOrderStatusEnum::PENDING->value]);
        $order->id = 10;
        $order->exists = true;

        $this->mercadoPagoWrapper
            ->shouldReceive('getPayment')
            ->once()
            ->with('payment-1')
            ->andReturn([
                'id' => 'payment-1',
                'status' => 'approved',
                'payment_type_id' => 'credit_card',
                'external_reference' => 'order-uuid-1',
                'date_approved' => '2026-01-10T10:00:00.000-04:00',
            ]);

        $this->paymentOrder
            ->shouldReceive('findByUuid')
            ->once()
            ->with('order-uuid-1', Mockery::any())
            ->andReturn($order);

        $this->healthReceiptService
            ->shouldReceive('createFromPaymentOrder')
            ->once()
            ->with(10);

        $webhook = new Webhook(['payload' => ['type' => 'payment', 'data' => ['id' => 'payment-1']]]);

        $this->service->handle($webhook);

        $this->assertSame(PaymentOrderStatusEnum::PAID, $order->status);
        $this->assertSame(PaymentMethodEnum::CARD, $order->payment_method);
        $this->assertSame('payment-1', $order->external_id);
        $this->assertNotNull($order->paid_at);
    }

    public function testItUpdatesOrderToPixPaymentMethod(): void
    {
        $order = new PaymentOrder(['status' => PaymentOrderStatusEnum::PENDING->value]);
        $order->id = 11;
        $order->exists = true;

        $this->mercadoPagoWrapper
            ->shouldReceive('getPayment')
            ->once()
            ->andReturn([
                'id' => 'payment-2',
                'status' => 'approved',
                'payment_type_id' => 'bank_transfer',
                'external_reference' => 'order-uuid-2',
                'date_approved' => '2026-01-10T10:00:00.000-04:00',
            ]);

        $this->paymentOrder
            ->shouldReceive('findByUuid')
            ->once()
            ->andReturn($order);

        $this->healthReceiptService->shouldReceive('createFromPaymentOrder')->once()->with(11);

        $webhook = new Webhook(['payload' => ['type' => 'payment', 'data' => ['id' => 'payment-2']]]);

        $this->service->handle($webhook);

        $this->assertSame(PaymentMethodEnum::PIX, $order->payment_method);
    }

    public function testItUpdatesOrderStatusWithoutPaymentMethodWhenNotPaid(): void
    {
        $order = new PaymentOrder(['status' => PaymentOrderStatusEnum::PENDING->value]);
        $order->id = 12;
        $order->exists = true;

        $this->mercadoPagoWrapper
            ->shouldReceive('getPayment')
            ->once()
            ->andReturn([
                'id' => 'payment-3',
                'status' => 'rejected',
                'external_reference' => 'order-uuid-3',
            ]);

        $this->paymentOrder
            ->shouldReceive('findByUuid')
            ->once()
            ->andReturn($order);

        $this->healthReceiptService->shouldNotReceive('createFromPaymentOrder');
        $this->healthReceiptService->shouldNotReceive('handleRefund');

        $webhook = new Webhook(['payload' => ['type' => 'payment', 'data' => ['id' => 'payment-3']]]);

        $this->service->handle($webhook);

        $this->assertSame(PaymentOrderStatusEnum::REJECTED, $order->status);
        $this->assertNull($order->payment_method);
        $this->assertNull($order->paid_at);
    }

    public function testItDoesNothingWhenPaymentHasNoExternalReference(): void
    {
        $this->mercadoPagoWrapper
            ->shouldReceive('getPayment')
            ->once()
            ->andReturn(['id' => 'payment-4', 'status' => 'approved']);

        $this->paymentOrder->shouldNotReceive('findByUuid');

        $webhook = new Webhook(['payload' => ['type' => 'payment', 'data' => ['id' => 'payment-4']]]);

        $this->service->handle($webhook);

        $this->addToAssertionCount(1);
    }

    public function testItDoesNothingWhenOrderNotFound(): void
    {
        $this->mercadoPagoWrapper
            ->shouldReceive('getPayment')
            ->once()
            ->andReturn(['id' => 'payment-5', 'status' => 'approved', 'external_reference' => 'unknown-uuid']);

        $this->paymentOrder
            ->shouldReceive('findByUuid')
            ->once()
            ->andReturn(null);

        $webhook = new Webhook(['payload' => ['type' => 'payment', 'data' => ['id' => 'payment-5']]]);

        $this->service->handle($webhook);

        $this->addToAssertionCount(1);
    }

    public function testItDoesNothingWhenStatusIsNotMapped(): void
    {
        $order = new PaymentOrder(['status' => PaymentOrderStatusEnum::PENDING->value]);
        $order->id = 13;
        $order->exists = true;

        $this->mercadoPagoWrapper
            ->shouldReceive('getPayment')
            ->once()
            ->andReturn(['id' => 'payment-6', 'status' => 'authorized', 'external_reference' => 'order-uuid-6']);

        $this->paymentOrder
            ->shouldReceive('findByUuid')
            ->once()
            ->andReturn($order);

        $webhook = new Webhook(['payload' => ['type' => 'payment', 'data' => ['id' => 'payment-6']]]);

        $this->service->handle($webhook);

        $this->assertSame(PaymentOrderStatusEnum::PENDING, $order->status);
    }

    public function testItCancelsHealthReceiptWhenOrderIsRefunded(): void
    {
        $order = new PaymentOrder(['status' => PaymentOrderStatusEnum::PAID->value]);
        $order->id = 13;
        $order->exists = true;

        $this->mercadoPagoWrapper
            ->shouldReceive('getPayment')
            ->once()
            ->andReturn([
                'id' => 'payment-4',
                'status' => 'refunded',
                'external_reference' => 'order-uuid-4',
            ]);

        $this->paymentOrder
            ->shouldReceive('findByUuid')
            ->once()
            ->andReturn($order);

        $this->healthReceiptService->shouldReceive('handleRefund')->once()->with(13);
        $this->healthReceiptService->shouldNotReceive('createFromPaymentOrder');

        $webhook = new Webhook(['payload' => ['type' => 'payment', 'data' => ['id' => 'payment-4']]]);

        $this->service->handle($webhook);

        $this->assertSame(PaymentOrderStatusEnum::REFUNDED, $order->status);
    }
}
