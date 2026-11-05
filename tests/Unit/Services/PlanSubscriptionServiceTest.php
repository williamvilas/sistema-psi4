<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\PlanUserEnum;
use App\Helpers\PlanHelper;
use App\Mail\PlanCancellationMail;
use App\Mail\PlanRenewalMail;
use App\Models\PlanSubscription;
use App\Models\User;
use App\Services\PlanSubscriptionService;
use App\Wrappers\MercadoPagoWrapper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class PlanSubscriptionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private $planSubscription;
    private $mercadoPagoWrapper;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planSubscription = Mockery::mock(PlanSubscription::class);
        $this->mercadoPagoWrapper = Mockery::mock(MercadoPagoWrapper::class);

        $this->planSubscription->shouldReceive('findScheduledCancelByUser')->andReturnNull()->byDefault();

        $this->service = new PlanSubscriptionService($this->planSubscription, $this->mercadoPagoWrapper);
    }

    public function testItCheckoutSuccessfully(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::FREE->value]);

        $this->mercadoPagoWrapper
            ->shouldReceive('createSubscription')
            ->once()
            ->andReturn([
                'id' => 'preapproval-123',
                'status' => 'pending',
                'init_point' => 'https://mercadopago.com/checkout/preapproval-123',
            ]);

        $this->planSubscription
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) use ($user) {
                return $data['user_id'] === $user->id
                    && $data['plan'] === PlanUserEnum::PROFESSIONAL->value
                    && $data['preapproval_id'] === 'preapproval-123'
                    && $data['status'] === 'pending'
                    && $data['init_point'] === 'https://mercadopago.com/checkout/preapproval-123';
            })
            ->andReturn($this->subscriptionWithId(10));

        $this->planSubscription->shouldReceive('cancelPendingByUser')->once()->with($user->id, 10)->andReturn(0);

        $link = $this->service->checkout(PlanUserEnum::PROFESSIONAL->value, $user);

        $this->assertSame('https://mercadopago.com/checkout/preapproval-123', $link);
    }

    public function testItThrowExceptionWhenPlanNotFound(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::FREE->value]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('plans.plan_not_found'));

        $this->service->checkout('inexistente', $user);
    }

    public function testItThrowExceptionWhenPlanHasNoPrice(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::PROFESSIONAL->value]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('plans.plan_free_no_checkout'));

        $this->service->checkout(PlanUserEnum::FREE->value, $user);
    }

    public function testItThrowExceptionWhenUserAlreadyOnPlan(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::PROFESSIONAL->value]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('plans.plan_free_no_checkout'));

        $this->service->checkout(PlanUserEnum::PROFESSIONAL->value, $user);
    }

    public function testItCheckoutMonthlyReplacesPendingSubscriptions(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::FREE->value]);

        $this->mercadoPagoWrapper
            ->shouldReceive('createSubscription')
            ->once()
            ->andReturn(['id' => 'preapproval-new', 'status' => 'pending', 'init_point' => 'https://mercadopago.com/new']);

        $this->planSubscription->shouldReceive('create')->once()->andReturn($this->subscriptionWithId(55));
        $this->planSubscription->shouldReceive('cancelPendingByUser')->once()->with($user->id, 55)->andReturn(1);

        $this->assertSame('https://mercadopago.com/new', $this->service->checkout(PlanUserEnum::PROFESSIONAL->value, $user));
    }

    public function testItDoesNotCancelPendingWhenCheckoutFails(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::FREE->value]);

        $this->mercadoPagoWrapper->shouldReceive('createSubscription')->once()->andThrow(new \DomainException('falha'));
        $this->planSubscription->shouldNotReceive('cancelPendingByUser');

        $this->expectException(\DomainException::class);

        $this->service->checkout(PlanUserEnum::PROFESSIONAL->value, $user);
    }

    public function testItCheckoutAnnualWithCardInInstallments(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::FREE->value]);

        $this->mercadoPagoWrapper
            ->shouldReceive('createPreference')
            ->once()
            ->withArgs(function (array $body) use ($user) {
                return $body['items'][0]['unit_price'] === 588.0
                    && $body['payment_methods']['installments'] === 12
                    && $body['payer']['email'] === $user->email
                    && ! in_array(['id' => 'credit_card'], $body['payment_methods']['excluded_payment_types'], true)
                    && in_array(['id' => 'bank_transfer'], $body['payment_methods']['excluded_payment_types'], true);
            })
            ->andReturn(['id' => 'pref-1', 'init_point' => 'https://mercadopago.com/pref-1']);

        $this->planSubscription
            ->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data) => $data['cycle'] === 'annual' && $data['payment_method'] === 'card' && $data['status'] === 'pending')
            ->andReturn($this->subscriptionWithId(20));

        $this->planSubscription->shouldReceive('cancelPendingByUser')->once()->with($user->id, 20)->andReturn(1);

        $link = $this->service->checkout(PlanUserEnum::PROFESSIONAL->value, $user, 'annual', 'card');

        $this->assertSame('https://mercadopago.com/pref-1', $link);
    }

    public function testItCheckoutAnnualWithPixAppliesExtraDiscount(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::FREE->value]);

        $this->mercadoPagoWrapper
            ->shouldReceive('createPreference')
            ->once()
            ->withArgs(fn (array $body) => $body['items'][0]['unit_price'] === 529.2
                && $body['payment_methods']['installments'] === 1
                && in_array(['id' => 'credit_card'], $body['payment_methods']['excluded_payment_types'], true))
            ->andReturn(['id' => 'pref-2', 'init_point' => 'https://mercadopago.com/pref-2']);

        $this->planSubscription->shouldReceive('create')->once()->andReturn($this->subscriptionWithId(21));
        $this->planSubscription->shouldReceive('cancelPendingByUser')->once()->with($user->id, 21)->andReturn(0);

        $this->assertSame('https://mercadopago.com/pref-2', $this->service->checkout(PlanUserEnum::PROFESSIONAL->value, $user, 'annual', 'pix'));
    }

    public function testItThrowExceptionWhenAnnualPaymentMethodInvalid(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::FREE->value]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('plans.payment_method_invalid'));

        $this->service->checkout(PlanUserEnum::PROFESSIONAL->value, $user, 'annual', 'boleto');
    }

    public function testItThrowExceptionWhenCycleInvalid(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::FREE->value]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('plans.cycle_invalid'));

        $this->service->checkout(PlanUserEnum::PROFESSIONAL->value, $user, 'quarterly');
    }

    public function testItThrowExceptionWhenAnnualAndUserOnMonthlyPlan(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::PROFESSIONAL->value]);

        $this->planSubscription->shouldReceive('findActiveAnnualByUser')->once()->andReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('plans.plan_change_unavailable'));

        $this->service->checkout(PlanUserEnum::PROFESSIONAL->value, $user, 'annual', 'card');
    }

    public function testItGetCheckoutOptionsForFreeUser(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::FREE->value]);

        $this->assertSame(
            ['monthly' => true, 'annual' => true, 'renewal' => false],
            $this->service->getCheckoutOptions(PlanUserEnum::PROFESSIONAL->value, $user),
        );
    }

    public function testItGetCheckoutOptionsForAnnualRenewalWindow(): void
    {
        $user = User::factory()->create([
            'current_plan' => PlanUserEnum::PROFESSIONAL->value,
            'plan_expiration_date' => now()->addDays(10),
        ]);

        $this->planSubscription->shouldReceive('findActiveAnnualByUser')->once()
            ->andReturn(new PlanSubscription(['status' => 'authorized', 'cycle' => 'annual']));

        $this->assertSame(
            ['monthly' => false, 'annual' => true, 'renewal' => true],
            $this->service->getCheckoutOptions(PlanUserEnum::PROFESSIONAL->value, $user),
        );
    }

    public function testItDoesNotAllowRenewalWhenAnnualPlanIsFarFromExpiring(): void
    {
        $user = User::factory()->create([
            'current_plan' => PlanUserEnum::PROFESSIONAL->value,
            'plan_expiration_date' => now()->addDays(100),
        ]);

        $this->planSubscription->shouldReceive('findActiveAnnualByUser')->once()
            ->andReturn(new PlanSubscription(['status' => 'authorized', 'cycle' => 'annual']));

        $this->assertFalse($this->service->canRenewAnnual(PlanUserEnum::PROFESSIONAL->value, $user));
    }

    public function testItGetPendingCheckoutWhenLatestIsPending(): void
    {
        $user = User::factory()->create();
        $latest = new PlanSubscription(['status' => 'pending', 'cycle' => 'monthly', 'init_point' => 'https://mp/x']);
        $latest->created_at = now()->subDays(10);

        $this->planSubscription->shouldReceive('findLatestByUser')->once()->andReturn($latest);

        $this->assertSame($latest, $this->service->getPendingCheckout($user));
    }

    public function testItHidesPendingCheckoutWhenLatestIsNotPending(): void
    {
        $user = User::factory()->create();

        $this->planSubscription->shouldReceive('findLatestByUser')->twice()->andReturn(
            new PlanSubscription(['status' => 'cancelled', 'cycle' => 'monthly', 'init_point' => 'https://mp/x']),
            new PlanSubscription(['status' => 'authorized', 'cycle' => 'monthly', 'init_point' => 'https://mp/x']),
        );

        $this->assertNull($this->service->getPendingCheckout($user));
        $this->assertNull($this->service->getPendingCheckout($user));
    }

    public function testItHidesPendingCheckoutWhenThereIsNoSubscription(): void
    {
        $this->planSubscription->shouldReceive('findLatestByUser')->once()->andReturn(null);

        $this->assertNull($this->service->getPendingCheckout(User::factory()->create()));
    }

    public function testItHidesPendingAnnualCheckoutWhenLinkExpired(): void
    {
        $latest = new PlanSubscription(['status' => 'pending', 'cycle' => 'annual', 'init_point' => 'https://mp/x']);
        $latest->created_at = now()->subDays(2);

        $this->planSubscription->shouldReceive('findLatestByUser')->once()->andReturn($latest);

        $this->assertNull($this->service->getPendingCheckout(User::factory()->create()));
    }

    public function testItShowsPendingAnnualCheckoutWhenLinkStillValid(): void
    {
        $latest = new PlanSubscription(['status' => 'pending', 'cycle' => 'annual', 'init_point' => 'https://mp/x']);
        $latest->created_at = now()->subHours(3);

        $this->planSubscription->shouldReceive('findLatestByUser')->once()->andReturn($latest);

        $this->assertSame($latest, $this->service->getPendingCheckout(User::factory()->create()));
    }

    public function testItExpiresAnnualPlans(): void
    {
        $user = User::factory()->create([
            'current_plan' => PlanUserEnum::PROFESSIONAL->value,
            'plan_activated_on' => now()->subYear(),
            'plan_expiration_date' => now()->subMinute(),
        ]);

        $subscription = PlanSubscription::query()->create([
            'user_id' => $user->id,
            'plan' => PlanUserEnum::PROFESSIONAL->value,
            'cycle' => 'annual',
            'external_reference' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'authorized',
        ]);

        $this->planSubscription->shouldReceive('getExpiredAnnual')->once()
            ->andReturn(new \Illuminate\Database\Eloquent\Collection([$subscription->load('user')]));

        $this->assertSame(1, $this->service->expireAnnualPlans());

        $user->refresh();
        $this->assertSame(PlanUserEnum::FREE->value, $user->current_plan);
        $this->assertNull($user->plan_expiration_date);
        $this->assertSame('cancelled', $subscription->refresh()->status->value);
    }

    public function testItExpiresNothingWhenNoAnnualPlanExpired(): void
    {
        $this->planSubscription->shouldReceive('getExpiredAnnual')->once()->andReturn(new \Illuminate\Database\Eloquent\Collection());

        $this->assertSame(0, $this->service->expireAnnualPlans());
    }

    /**
     * @dataProvider renewalMilestoneProvider
     */
    public function testItNotifyAnnualRenewalsOnlyOnMilestones(int $daysToExpire, ?int $alreadyNotified, bool $shouldSend, ?int $expectedMilestone): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'current_plan' => PlanUserEnum::PROFESSIONAL->value,
            // Ancorado no início do dia: perto da meia-noite UTC, now() + 2 h já cairia no dia seguinte
            'plan_expiration_date' => now()->startOfDay()->addDays($daysToExpire)->addHours(2),
        ]);

        $subscription = PlanSubscription::query()->create([
            'user_id' => $user->id,
            'plan' => PlanUserEnum::PROFESSIONAL->value,
            'cycle' => 'annual',
            'external_reference' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'authorized',
            'renewal_notified_days' => $alreadyNotified,
        ]);

        $this->planSubscription->shouldReceive('getAnnualExpiringUntil')->once()
            ->andReturn(new \Illuminate\Database\Eloquent\Collection([$subscription->load('user')]));

        $total = $this->service->notifyAnnualRenewals();

        if ($shouldSend) {
            $this->assertSame(1, $total);
            Mail::assertQueued(PlanRenewalMail::class, fn (PlanRenewalMail $mail) => $mail->hasTo($user->email));
            $this->assertSame($expectedMilestone, $subscription->refresh()->renewal_notified_days);
        } else {
            $this->assertSame(0, $total);
            Mail::assertNothingQueued();
        }
    }

    public static function renewalMilestoneProvider(): array
    {
        return [
            'faltam 15 dias, sem aviso anterior' => [15, null, true, 15],
            'faltam 10 dias, ainda sem aviso (comando ficou parado)' => [10, null, true, 15],
            'faltam 10 dias, marco de 15 já enviado' => [10, 15, false, null],
            'faltam 3 dias, marco de 15 enviado' => [3, 15, true, 3],
            'faltam 3 dias, marco de 3 já enviado' => [3, 3, false, null],
            'vence hoje, marco de 3 enviado' => [0, 3, true, 0],
            'vence hoje, marco de 0 já enviado' => [0, 0, false, null],
            'faltam 30 dias (fora da janela)' => [30, null, false, null],
        ];
    }

    public function testItCalculatesPaymentFeeByPlan(): void
    {
        $this->assertSame(2.0, PlanHelper::paymentFeePercent('free'));
        $this->assertSame(0.0, PlanHelper::paymentFeePercent('professional'));
        $this->assertSame(3.0, PlanHelper::paymentFee('free', 150.0));
        $this->assertSame(0.0, PlanHelper::paymentFee('professional', 150.0));
        $this->assertSame(0.0, PlanHelper::paymentFee('inexistente', 150.0));
    }

    public function testItCalculatesAnnualPrices(): void
    {
        $this->assertSame(588.0, PlanHelper::annualTotal('professional'));
        $this->assertSame(529.2, PlanHelper::annualPixTotal('professional'));
        $this->assertSame(49.0, PlanHelper::annualInstallmentValue('professional'));
        $this->assertSame(49.0, PlanHelper::annualMonthlyEquivalent('professional'));
        $this->assertSame(30, PlanHelper::annualDiscount('professional'));
        $this->assertSame(0.0, PlanHelper::annualTotal('free'));
        $this->assertSame(0, PlanHelper::annualDiscount('free'));
    }

    public function testItCancelRenewalMonthlySuccessfully(): void
    {
        Mail::fake();

        $expiration = now()->addDays(10);
        $user = User::factory()->create(['current_plan' => PlanUserEnum::PROFESSIONAL->value, 'plan_expiration_date' => $expiration]);
        $subscription = $this->createSubscription($user, 'monthly', ['preapproval_id' => 'pre-1']);

        $this->planSubscription->shouldReceive('findAuthorizedByUser')->once()->andReturn($subscription);
        $this->mercadoPagoWrapper->shouldReceive('cancelSubscription')->once()->with('pre-1');

        $date = $this->service->cancelRenewal($user);

        $this->assertSame($expiration->format('d/m/Y'), $date);
        $this->assertNotNull($subscription->refresh()->cancel_at_period_end_at);
        Mail::assertQueued(PlanCancellationMail::class, fn (PlanCancellationMail $mail) => $mail->hasTo($user->email) && $mail->expirationDate === $date);
    }

    public function testItCancelRenewalAnnualWithoutCallingWrapper(): void
    {
        Mail::fake();

        $user = User::factory()->create(['current_plan' => PlanUserEnum::PROFESSIONAL->value, 'plan_expiration_date' => now()->addMonths(6)]);
        $subscription = $this->createSubscription($user, 'annual');

        $this->planSubscription->shouldReceive('findAuthorizedByUser')->once()->andReturn($subscription);
        $this->mercadoPagoWrapper->shouldNotReceive('cancelSubscription');

        $this->service->cancelRenewal($user);

        $this->assertNotNull($subscription->refresh()->cancel_at_period_end_at);
        Mail::assertQueued(PlanCancellationMail::class);
    }

    public function testItThrowExceptionWhenNoActiveSubscriptionToCancel(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::FREE->value]);

        $this->planSubscription->shouldReceive('findAuthorizedByUser')->once()->andReturnNull();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('plans.cancel_renewal_unavailable'));

        $this->service->cancelRenewal($user);
    }

    public function testItThrowExceptionWhenRenewalAlreadyScheduled(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::PROFESSIONAL->value, 'plan_expiration_date' => now()->addDays(5)]);
        $subscription = $this->createSubscription($user, 'annual', ['cancel_at_period_end_at' => now()]);

        $this->planSubscription->shouldReceive('findAuthorizedByUser')->once()->andReturn($subscription);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('plans.cancel_renewal_already_scheduled'));

        $this->service->cancelRenewal($user);
    }

    public function testItDoesNotPersistCancellationWhenWrapperFails(): void
    {
        Mail::fake();

        $user = User::factory()->create(['current_plan' => PlanUserEnum::PROFESSIONAL->value, 'plan_expiration_date' => now()->addDays(10)]);
        $subscription = $this->createSubscription($user, 'monthly', ['preapproval_id' => 'pre-1']);

        $this->planSubscription->shouldReceive('findAuthorizedByUser')->once()->andReturn($subscription);
        $this->mercadoPagoWrapper->shouldReceive('cancelSubscription')->once()->andThrow(new \DomainException(__('plans.cancel_renewal_failed')));

        try {
            $this->service->cancelRenewal($user);
            $this->fail('Deveria lançar DomainException');
        } catch (\DomainException) {
        }

        $this->assertNull($subscription->refresh()->cancel_at_period_end_at);
        Mail::assertNothingQueued();
    }

    public function testItBlocksCheckoutWhenRenewalCancellationScheduled(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::PROFESSIONAL->value]);

        $this->planSubscription->shouldReceive('findScheduledCancelByUser')->once()->andReturn($this->subscriptionWithId(1));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('plans.cancel_renewal_blocked'));

        $this->service->getCheckoutOptions(PlanUserEnum::PROFESSIONAL->value, $user);
    }

    public function testItExpireCancelledMonthlyPlansSuccessfully(): void
    {
        $user = User::factory()->create([
            'current_plan' => PlanUserEnum::PROFESSIONAL->value,
            'plan_activated_on' => now()->subDays(31),
            'plan_expiration_date' => now()->subMinute(),
        ]);
        $subscription = $this->createSubscription($user, 'monthly', ['status' => 'cancelled', 'cancel_at_period_end_at' => now()->subDays(20)]);

        $this->planSubscription->shouldReceive('getExpiredCancelledMonthly')->once()
            ->andReturn(new \Illuminate\Database\Eloquent\Collection([$subscription->load('user')]));

        $this->assertSame(1, $this->service->expireCancelledMonthlyPlans());

        $user->refresh();
        $this->assertSame(PlanUserEnum::FREE->value, $user->current_plan);
        $this->assertNull($user->plan_expiration_date);
    }

    public function testItExpiresNothingWhenNoCancelledMonthlyPlanExpired(): void
    {
        $this->planSubscription->shouldReceive('getExpiredCancelledMonthly')->once()->andReturn(new \Illuminate\Database\Eloquent\Collection());

        $this->assertSame(0, $this->service->expireCancelledMonthlyPlans());
    }

    public function testItGetCancelableSubscriptionOnlyWithoutScheduledCancellation(): void
    {
        $user = User::factory()->create();
        $open = $this->createSubscription($user, 'monthly');
        $scheduled = $this->createSubscription($user, 'monthly', ['cancel_at_period_end_at' => now()]);

        $this->planSubscription->shouldReceive('findAuthorizedByUser')->twice()->andReturn($open, $scheduled);

        $this->assertSame($open, $this->service->getCancelableSubscription($user));
        $this->assertNull($this->service->getCancelableSubscription($user));
    }

    public function testItGetPlansForDisplayForFreeUser(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::FREE->value]);

        $this->planSubscription->shouldReceive('findAuthorizedByUser')->once()->andReturnNull();

        $plans = $this->plansForDisplay($user);
        $professional = $plans['professional'];

        // O Grátis aparece ao lado do Profissional, como plano atual
        $this->assertSame(['free', 'professional'], array_keys($plans));
        $this->assertTrue($plans['free']['current']);
        $this->assertSame('current', $plans['free']['action']);
        $this->assertNull($plans['free']['annual']);
        $this->assertFalse($professional['current']);
        $this->assertSame('subscribe', $professional['action']);
        $this->assertSame(PlanHelper::annualMonthlyEquivalent(PlanUserEnum::PROFESSIONAL->value), $professional['annual']['monthly_equivalent']);
        $this->assertSame(PlanHelper::annualTotal(PlanUserEnum::PROFESSIONAL->value), $professional['annual']['total']);
    }

    public function testItGetPlansForDisplayListsProfessionalFeatures(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::FREE->value]);

        $this->planSubscription->shouldReceive('findAuthorizedByUser')->once()->andReturnNull();

        ['professional' => $professional] = $this->plansForDisplay($user);

        $this->assertSame(__('plans.agenda_unlimited'), $professional['features'][0]['label']);

        $ai = __('plans.features.ai_medical_records', ['model' => __('plans.model_ia')]);
        $this->assertContains(['label' => $ai, 'included' => true], $professional['features']);
        $this->assertContains(['label' => __('plans.features.public_booking'), 'included' => true], $professional['features']);
    }

    public function testItGetPlansForDisplayWithCancelableSubscription(): void
    {
        $user = User::factory()->create([
            'current_plan' => PlanUserEnum::PROFESSIONAL->value,
            'plan_expiration_date' => now()->addDays(20),
        ]);
        $subscription = $this->createSubscription($user, 'monthly');

        $this->planSubscription->shouldReceive('findAuthorizedByUser')->once()->andReturn($subscription);
        $this->planSubscription->shouldReceive('findActiveAnnualByUser')->once()->andReturnNull();

        ['professional' => $professional] = $this->plansForDisplay($user);

        $this->assertSame('current', $professional['action']);
        $this->assertTrue($professional['can_cancel_renewal']);
        $this->assertFalse($professional['renewal_cancelled']);
        $this->assertSame(now()->addDays(20)->format('d/m/Y'), $professional['expiration_date']);
    }

    public function testItGetPlansForDisplayWithAnnualRenewalAvailable(): void
    {
        $user = User::factory()->create([
            'current_plan' => PlanUserEnum::PROFESSIONAL->value,
            'plan_expiration_date' => now()->addDays(10),
        ]);

        $this->planSubscription->shouldReceive('findAuthorizedByUser')->once()->andReturnNull();
        $this->planSubscription->shouldReceive('findActiveAnnualByUser')->once()
            ->andReturn(new PlanSubscription(['status' => 'authorized', 'cycle' => 'annual']));

        ['professional' => $professional] = $this->plansForDisplay($user);

        $this->assertSame('renew', $professional['action']);
    }

    public function testItGetPlansForDisplayWithScheduledCancellation(): void
    {
        $user = User::factory()->create([
            'current_plan' => PlanUserEnum::PROFESSIONAL->value,
            'plan_expiration_date' => now()->addDays(5),
        ]);
        $scheduled = $this->createSubscription($user, 'monthly', ['cancel_at_period_end_at' => now()]);

        $this->planSubscription->shouldReceive('findAuthorizedByUser')->once()->andReturn($scheduled);
        $this->planSubscription->shouldReceive('findScheduledCancelByUser')->andReturn($scheduled);

        ['professional' => $professional] = $this->plansForDisplay($user);

        $this->assertSame('current', $professional['action']);
        $this->assertFalse($professional['can_cancel_renewal']);
        $this->assertTrue($professional['renewal_cancelled']);
    }

    public function testItGetPlansForDisplayShowsTotalPaymentFeeByMethod(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::FREE->value]);
        config(['plans.plans' => [
            'free' => ['payment_fee_percent' => 2, 'price' => 0, 'extras' => []],
            'professional' => ['payment_fee_percent' => 0, 'price' => 49, 'extras' => []],
        ]]);
        config(['mercadopago.fees.pix' => 0.99, 'mercadopago.fees.card' => 4.98]);

        $this->planSubscription->shouldReceive('findAuthorizedByUser')->once()->andReturnNull();

        ['free' => $free, 'professional' => $professional] = $this->plansForDisplay($user);

        $this->assertSame([
            ['label' => __('plans.payment_fee_pix', ['percent' => '2,99%']), 'included' => true],
            ['label' => __('plans.payment_fee_card', ['percent' => '6,98%']), 'included' => true],
        ], $free['features']);
        $this->assertSame([
            ['label' => __('plans.payment_fee_pix', ['percent' => '0,99%']), 'included' => true, 'note' => __('plans.payment_fee_mercado_pago_only')],
            ['label' => __('plans.payment_fee_card', ['percent' => '4,98%']), 'included' => true, 'note' => __('plans.payment_fee_mercado_pago_only')],
        ], $professional['features']);
    }

    public function testItGetPlansForDisplayListsFreePlanWithLimits(): void
    {
        $user = User::factory()->create(['current_plan' => PlanUserEnum::FREE->value]);
        config(['plans.plans' => [
            'free' => ['total_agenda' => 3, 'price' => 0, 'extras' => []],
            'professional' => ['total_agenda' => -1, 'price' => 49, 'emphasis' => true, 'extras' => ['Texto livre']],
        ]]);

        $this->planSubscription->shouldReceive('findAuthorizedByUser')->once()->andReturnNull();

        ['free' => $free, 'professional' => $professional] = $this->plansForDisplay($user);

        $this->assertSame('current', $free['action']);
        $this->assertSame([
            ['label' => trans_choice('plans.total_agenda', 3, ['total' => 3]), 'included' => true],
            ['label' => 'Texto livre', 'included' => false],
        ], $free['features']);
        $this->assertSame('subscribe', $professional['action']);
        $this->assertSame([
            ['label' => __('plans.agenda_unlimited'), 'included' => true],
            ['label' => 'Texto livre', 'included' => true],
        ], $professional['features']);
        $this->assertTrue($professional['emphasis']);
        $this->assertNull($professional['annual']);
    }

    private function plansForDisplay(User $user): array
    {
        return collect($this->service->getPlansForDisplay($user))->keyBy('key')->all();
    }

    private function createSubscription(User $user, string $cycle, array $attributes = []): PlanSubscription
    {
        return PlanSubscription::query()->create($attributes + [
            'user_id' => $user->id,
            'plan' => PlanUserEnum::PROFESSIONAL->value,
            'cycle' => $cycle,
            'external_reference' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'authorized',
        ]);
    }

    private function subscriptionWithId(int $id): PlanSubscription
    {
        $subscription = new PlanSubscription();
        $subscription->id = $id;

        return $subscription;
    }
}
