<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\PlanUserEnum;
use App\Jobs\RegisterLog;
use App\Models\PlanSubscription;
use App\Models\User;
use App\Models\Webhook;
use App\Services\ProcessMercadoPagoPlanWebhookService;
use App\Wrappers\MercadoPagoWrapper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class ProcessMercadoPagoPlanWebhookServiceTest extends TestCase
{
    use DatabaseTransactions;

    private $planSubscriptionModel;
    private $mercadoPagoWrapper;
    private $service;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planSubscriptionModel = Mockery::mock(PlanSubscription::class);
        $this->mercadoPagoWrapper = Mockery::mock(MercadoPagoWrapper::class);

        $this->service = new ProcessMercadoPagoPlanWebhookService($this->planSubscriptionModel, $this->mercadoPagoWrapper);

        $this->user = User::factory()->create(['current_plan' => PlanUserEnum::FREE->value]);

        // Sem outra assinatura vigente (sem duplicidade) por padrão
        $this->planSubscriptionModel->shouldReceive('findOtherAuthorizedByUser')->andReturn(null)->byDefault();
    }

    public function testItThrowExceptionWhenDataIdMissing(): void
    {
        $webhook = new Webhook(['payload' => ['type' => 'subscription_preapproval', 'data' => []]]);

        $this->expectException(\DomainException::class);

        $this->service->handle($webhook);
    }

    public function testItActivatesPlanWhenPreapprovalIsAuthorized(): void
    {
        $subscription = $this->persistSubscription(PlanUserEnum::PROFESSIONAL, 'preapproval-1', 'pending');

        $this->planSubscriptionModel
            ->shouldReceive('findByPreapprovalId')
            ->once()
            ->with('preapproval-1', Mockery::any())
            ->andReturn($subscription);

        $this->mercadoPagoWrapper
            ->shouldReceive('getSubscription')
            ->once()
            ->with('preapproval-1')
            ->andReturn(['status' => 'authorized', 'external_reference' => $subscription->external_reference]);

        $webhook = new Webhook(['payload' => ['type' => 'subscription_preapproval', 'data' => ['id' => 'preapproval-1']]]);

        $result = $this->service->handle($webhook);

        $this->assertNull($result);

        $this->user->refresh();
        $this->assertSame(PlanUserEnum::PROFESSIONAL->value, $this->user->current_plan);
        $this->assertNotNull($this->user->plan_activated_on);

        $subscription->refresh();
        $this->assertSame('authorized', $subscription->status->value);
    }

    public function testItCancelsScheduledWhenPreapprovalIsCancelled(): void
    {
        $this->user->update([
            'current_plan' => PlanUserEnum::PROFESSIONAL->value,
            'plan_activated_on' => now()->subDays(35),
            'plan_expiration_date' => now()->addDays(5),
        ]);

        $subscription = $this->persistSubscription(PlanUserEnum::PROFESSIONAL, 'preapproval-2', 'authorized');

        $this->planSubscriptionModel
            ->shouldReceive('findByPreapprovalId')
            ->once()
            ->andReturn($subscription);

        $this->mercadoPagoWrapper
            ->shouldReceive('getSubscription')
            ->once()
            ->andReturn(['status' => 'cancelled', 'external_reference' => $subscription->external_reference]);

        $webhook = new Webhook(['payload' => ['type' => 'subscription_preapproval', 'data' => ['id' => 'preapproval-2']]]);

        $this->service->handle($webhook);

        $this->user->refresh();
        $this->assertSame(PlanUserEnum::FREE->value, $this->user->current_plan);
        $this->assertNull($this->user->plan_expiration_date);
    }

    public function testItActivatesOnProcessedAuthorizedPayment(): void
    {
        $subscription = $this->persistSubscription(PlanUserEnum::PROFESSIONAL, 'preapproval-3', 'authorized');

        $this->planSubscriptionModel
            ->shouldReceive('findByPreapprovalId')
            ->once()
            ->with('preapproval-3', Mockery::any())
            ->andReturn($subscription);

        $this->mercadoPagoWrapper
            ->shouldReceive('getAuthorizedPayment')
            ->once()
            ->with('authorized-payment-1')
            ->andReturn(['id' => 'authorized-payment-1', 'preapproval_id' => 'preapproval-3', 'status' => 'processed', 'transaction_amount' => 49.0]);

        $webhook = new Webhook(['payload' => ['type' => 'subscription_authorized_payment', 'data' => ['id' => 'authorized-payment-1']]]);

        $result = $this->service->handle($webhook);

        $this->assertIsArray($result);
        $this->assertSame('authorized-payment-1', $result['resource']['id']);
        $this->assertTrue($subscription->is($result['subscription']));

        $this->user->refresh();
        $this->assertSame(PlanUserEnum::PROFESSIONAL->value, $this->user->current_plan);
    }

    public function testItDoesNothingWhenAuthorizedPaymentIsNotProcessed(): void
    {
        $subscription = $this->persistSubscription(PlanUserEnum::PROFESSIONAL, 'preapproval-4', 'authorized');

        $this->planSubscriptionModel
            ->shouldReceive('findByPreapprovalId')
            ->once()
            ->andReturn($subscription);

        $this->mercadoPagoWrapper
            ->shouldReceive('getAuthorizedPayment')
            ->once()
            ->andReturn(['id' => 'authorized-payment-2', 'preapproval_id' => 'preapproval-4', 'status' => 'pending']);

        $webhook = new Webhook(['payload' => ['type' => 'subscription_authorized_payment', 'data' => ['id' => 'authorized-payment-2']]]);

        $result = $this->service->handle($webhook);

        $this->assertNull($result);
    }

    public function testItActivatesAnnualPlanWhenPaymentIsApproved(): void
    {
        $subscription = $this->persistAnnualSubscription('pending');

        $this->planSubscriptionModel->shouldReceive('findByExternalReference')->once()->andReturn($subscription);
        $this->planSubscriptionModel->shouldReceive('cancelOtherAuthorizedAnnual')->once()->with($this->user->id, $subscription->id)->andReturn(0);
        $this->mercadoPagoWrapper->shouldReceive('getPayment')->once()->with('pay-1')
            ->andReturn(['id' => 'pay-1', 'status' => 'approved', 'external_reference' => $subscription->external_reference]);

        $result = $this->service->handlePayment($this->paymentWebhook('pay-1'));

        $this->assertTrue($result['approved']);

        $this->user->refresh();
        $this->assertSame(PlanUserEnum::PROFESSIONAL->value, $this->user->current_plan);
        $this->assertTrue(\Carbon\Carbon::parse($this->user->plan_expiration_date)->between(now()->addDays(364), now()->addDays(366)));

        $subscription->refresh();
        $this->assertSame('authorized', $subscription->status->value);
        $this->assertSame('pay-1', $subscription->payment_id);
    }

    public function testItExtendsExpirationWhenAnnualRenewalIsApprovedBeforeExpiring(): void
    {
        $this->user->update([
            'current_plan' => PlanUserEnum::PROFESSIONAL->value,
            'plan_expiration_date' => now()->addDays(10),
        ]);
        $subscription = $this->persistAnnualSubscription('pending');

        $this->planSubscriptionModel->shouldReceive('findByExternalReference')->once()->andReturn($subscription);
        $this->planSubscriptionModel->shouldReceive('cancelOtherAuthorizedAnnual')->once()->andReturn(1);
        $this->mercadoPagoWrapper->shouldReceive('getPayment')->once()
            ->andReturn(['id' => 'pay-2', 'status' => 'approved', 'external_reference' => $subscription->external_reference]);

        $this->service->handlePayment($this->paymentWebhook('pay-2'));

        $this->user->refresh();
        $this->assertTrue(\Carbon\Carbon::parse($this->user->plan_expiration_date)->between(now()->addDays(374), now()->addDays(376)));
    }

    public function testItDoesNotActivateAgainWhenApprovedPaymentIsRepeated(): void
    {
        $subscription = $this->persistAnnualSubscription('authorized', 'pay-3');

        $this->planSubscriptionModel->shouldReceive('findByExternalReference')->once()->andReturn($subscription);
        $this->planSubscriptionModel->shouldNotReceive('cancelOtherAuthorizedAnnual');
        $this->mercadoPagoWrapper->shouldReceive('getPayment')->once()
            ->andReturn(['id' => 'pay-3', 'status' => 'approved', 'external_reference' => $subscription->external_reference]);

        $result = $this->service->handlePayment($this->paymentWebhook('pay-3'));

        $this->assertFalse($result['approved']);
        $this->assertSame(PlanUserEnum::FREE->value, $this->user->refresh()->current_plan);
    }

    public function testItDoesNotActivateAnnualPlanWhenPaymentIsPending(): void
    {
        $subscription = $this->persistAnnualSubscription('pending');

        $this->planSubscriptionModel->shouldReceive('findByExternalReference')->once()->andReturn($subscription);
        $this->mercadoPagoWrapper->shouldReceive('getPayment')->once()
            ->andReturn(['id' => 'pay-4', 'status' => 'pending', 'external_reference' => $subscription->external_reference]);

        $result = $this->service->handlePayment($this->paymentWebhook('pay-4'));

        $this->assertFalse($result['approved']);
        $this->assertSame(PlanUserEnum::FREE->value, $this->user->refresh()->current_plan);
    }

    public function testItCancelsAnnualSubscriptionWhenPaymentIsCancelled(): void
    {
        $subscription = $this->persistAnnualSubscription('pending');

        $this->planSubscriptionModel->shouldReceive('findByExternalReference')->once()->andReturn($subscription);
        $this->mercadoPagoWrapper->shouldReceive('getPayment')->once()
            ->andReturn(['id' => 'pay-5', 'status' => 'cancelled', 'external_reference' => $subscription->external_reference]);

        $this->service->handlePayment($this->paymentWebhook('pay-5'));

        $this->assertSame('cancelled', $subscription->refresh()->status->value);
    }

    public function testItReturnsNullWhenPaymentIsNotFromAPlanSubscription(): void
    {
        $this->planSubscriptionModel->shouldReceive('findByExternalReference')->once()->andReturn(null);
        $this->mercadoPagoWrapper->shouldReceive('getPayment')->once()
            ->andReturn(['id' => 'pay-6', 'status' => 'approved', 'external_reference' => 'uuid-de-cobranca-de-paciente']);

        $this->assertNull($this->service->handlePayment($this->paymentWebhook('pay-6')));
    }

    public function testItReturnsNullWhenPaymentHasNoExternalReference(): void
    {
        $this->mercadoPagoWrapper->shouldReceive('getPayment')->once()->andReturn(['id' => 'pay-7', 'status' => 'approved']);

        $this->assertNull($this->service->handlePayment($this->paymentWebhook('pay-7')));
    }

    public function testItThrowExceptionWhenPaymentDataIdMissing(): void
    {
        $this->expectException(\DomainException::class);

        $this->service->handlePayment(new Webhook(['payload' => ['type' => 'payment', 'data' => []]]));
    }

    public function testItDoesNotChangePlanWhenPendingSubscriptionIsCancelled(): void
    {
        $this->user->update(['current_plan' => PlanUserEnum::PROFESSIONAL->value, 'plan_activated_on' => now()->subDays(40), 'plan_expiration_date' => now()->addDays(300)]);
        $subscription = $this->persistSubscription(PlanUserEnum::PROFESSIONAL, 'preapproval-old', 'pending');

        $this->planSubscriptionModel->shouldReceive('findByPreapprovalId')->once()->andReturn($subscription);
        $this->mercadoPagoWrapper->shouldReceive('getSubscription')->once()
            ->andReturn(['status' => 'cancelled', 'external_reference' => $subscription->external_reference]);

        $this->service->handle(new Webhook(['payload' => ['type' => 'subscription_preapproval', 'data' => ['id' => 'preapproval-old']]]));

        $this->user->refresh();
        $this->assertSame(PlanUserEnum::PROFESSIONAL->value, $this->user->current_plan);
        $this->assertNotNull($this->user->plan_expiration_date);
        $this->assertSame('cancelled', $subscription->refresh()->status->value);
    }

    public function testItAlertsWhenReplacedAnnualCheckoutIsPaidWhileAnotherIsActive(): void
    {
        Bus::fake();

        $subscription = $this->persistAnnualSubscription('cancelled');
        $other = $this->persistAnnualSubscription('authorized', 'pay-first');

        $this->planSubscriptionModel->shouldReceive('findByExternalReference')->once()->andReturn($subscription);
        $this->planSubscriptionModel->shouldReceive('findOtherAuthorizedByUser')->once()->andReturn($other);
        $this->planSubscriptionModel->shouldReceive('cancelOtherAuthorizedAnnual')->once()->andReturn(1);
        $this->mercadoPagoWrapper->shouldReceive('getPayment')->once()
            ->andReturn(['id' => 'pay-dup', 'status' => 'approved', 'external_reference' => $subscription->external_reference]);

        $result = $this->service->handlePayment($this->paymentWebhook('pay-dup'));

        $this->assertTrue($result['approved']);
        Bus::assertDispatchedSync(RegisterLog::class);
    }

    public function testItAlertsWhenMonthlyIsAuthorizedWhileAnnualIsActive(): void
    {
        Bus::fake();

        $annual = $this->persistAnnualSubscription('authorized', 'pay-first');
        $subscription = $this->persistSubscription(PlanUserEnum::PROFESSIONAL, 'preapproval-dup', 'pending');

        $this->planSubscriptionModel->shouldReceive('findByPreapprovalId')->once()->andReturn($subscription);
        $this->planSubscriptionModel->shouldReceive('findOtherAuthorizedByUser')->once()->andReturn($annual);
        $this->mercadoPagoWrapper->shouldReceive('getSubscription')->once()
            ->andReturn(['status' => 'authorized', 'external_reference' => $subscription->external_reference]);

        $this->service->handle(new Webhook(['payload' => ['type' => 'subscription_preapproval', 'data' => ['id' => 'preapproval-dup']]]));

        Bus::assertDispatchedSync(RegisterLog::class);
    }

    public function testItDoesNotAlertOnAnnualRenewalBeforeExpiring(): void
    {
        Bus::fake();

        $this->user->update(['current_plan' => PlanUserEnum::PROFESSIONAL->value, 'plan_expiration_date' => now()->addDays(5)]);
        $subscription = $this->persistAnnualSubscription('pending');
        $other = $this->persistAnnualSubscription('authorized', 'pay-first');

        $this->planSubscriptionModel->shouldReceive('findByExternalReference')->once()->andReturn($subscription);
        $this->planSubscriptionModel->shouldReceive('findOtherAuthorizedByUser')->once()->andReturn($other);
        $this->planSubscriptionModel->shouldReceive('cancelOtherAuthorizedAnnual')->once()->andReturn(1);
        $this->mercadoPagoWrapper->shouldReceive('getPayment')->once()
            ->andReturn(['id' => 'pay-renew', 'status' => 'approved', 'external_reference' => $subscription->external_reference]);

        $this->service->handlePayment($this->paymentWebhook('pay-renew'));

        Bus::assertNotDispatchedSync(RegisterLog::class);
    }

    public function testItDoesNotAlertOnRecurringChargeOfActiveSubscription(): void
    {
        Bus::fake();

        $subscription = $this->persistSubscription(PlanUserEnum::PROFESSIONAL, 'preapproval-rec', 'authorized');

        $this->planSubscriptionModel->shouldReceive('findByPreapprovalId')->once()->andReturn($subscription);
        $this->planSubscriptionModel->shouldNotReceive('findOtherAuthorizedByUser');
        $this->mercadoPagoWrapper->shouldReceive('getAuthorizedPayment')->once()
            ->andReturn(['id' => 'ap-9', 'preapproval_id' => 'preapproval-rec', 'status' => 'processed']);

        $this->service->handle(new Webhook(['payload' => ['type' => 'subscription_authorized_payment', 'data' => ['id' => 'ap-9']]]));

        Bus::assertNotDispatchedSync(RegisterLog::class);
    }

    private function paymentWebhook(string $paymentId): Webhook
    {
        return new Webhook(['payload' => ['type' => 'payment', 'data' => ['id' => $paymentId]]]);
    }

    private function persistAnnualSubscription(string $status, ?string $paymentId = null): PlanSubscription
    {
        return PlanSubscription::query()->create([
            'user_id' => $this->user->id,
            'plan' => PlanUserEnum::PROFESSIONAL->value,
            'cycle' => 'annual',
            'payment_method' => 'card',
            'external_reference' => (string) \Illuminate\Support\Str::uuid(),
            'payment_id' => $paymentId,
            'status' => $status,
        ]);
    }

    private function persistSubscription(PlanUserEnum $plan, string $preapprovalId, string $status): PlanSubscription
    {
        return PlanSubscription::query()->create([
            'user_id' => $this->user->id,
            'plan' => $plan->value,
            'external_reference' => (string) \Illuminate\Support\Str::uuid(),
            'preapproval_id' => $preapprovalId,
            'status' => $status,
        ]);
    }
}
