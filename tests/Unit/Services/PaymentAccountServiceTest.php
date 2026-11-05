<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\PaymentAccountsStatusEnum;
use App\Enums\PaymentGatewayEnum;
use App\Models\PaymentAccount;
use App\Models\User;
use App\Services\PaymentAccountService;
use App\Wrappers\MercadoPagoConnectWrapper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class PaymentAccountServiceTest extends TestCase
{
    use DatabaseTransactions;

    private $paymentAccount;
    private $connectWrapper;
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentAccount = Mockery::mock(PaymentAccount::class);
        $this->connectWrapper = Mockery::mock(MercadoPagoConnectWrapper::class);

        $this->service = new PaymentAccountService($this->paymentAccount, $this->connectWrapper);
    }

    public function testItBuildAuthorizationUrlReturnsWrapperUrl(): void
    {
        $this->connectWrapper
            ->shouldReceive('buildAuthorizationUrl')
            ->once()
            ->andReturnUsing(fn (string $state) => 'https://auth.mercadopago.com.br/authorization?state='.$state);

        $url = $this->service->buildAuthorizationUrl(1);

        $this->assertStringStartsWith('https://auth.mercadopago.com.br/authorization?state=', $url);
    }

    public function testItConnectSuccessfullyCreatesNewAccount(): void
    {
        $user = User::factory()->create();
        $state = $this->captureAuthorizationState($user->id);

        $this->connectWrapper
            ->shouldReceive('exchangeAuthorizationCode')
            ->once()
            ->with('auth-code')
            ->andReturn([
                'access_token' => 'access-token-1',
                'refresh_token' => 'refresh-token-1',
                'expires_in' => 3600,
                'user_id' => 555,
                'public_key' => 'public-key-1',
                'scope' => 'read write',
            ]);

        $this->paymentAccount
            ->shouldReceive('newQuery')
            ->once()
            ->andReturnSelf();

        $this->paymentAccount
            ->shouldReceive('firstOrNew')
            ->once()
            ->with(['user_id' => $user->id, 'provider' => PaymentGatewayEnum::MERCADO_PAGO->value])
            ->andReturn(new PaymentAccount(['user_id' => $user->id, 'provider' => PaymentGatewayEnum::MERCADO_PAGO->value]));

        $this->service->connect(code: 'auth-code', state: $state, userId: $user->id);

        $this->assertDatabaseHas('payment_accounts', [
            'user_id' => $user->id,
            'provider' => PaymentGatewayEnum::MERCADO_PAGO->value,
            'mp_user_id' => 555,
            'status' => PaymentAccountsStatusEnum::INTEGRATED->value,
        ]);
    }

    public function testItThrowExceptionWhenStateNotFound(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('payments.oauth_state_invalid'));

        $this->service->connect(code: 'auth-code', state: 'unknown-state', userId: 1);
    }

    public function testItThrowExceptionWhenStateBelongsToDifferentUser(): void
    {
        $state = $this->captureAuthorizationState(1);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('payments.oauth_state_invalid'));

        $this->service->connect(code: 'auth-code', state: $state, userId: 2);
    }

    public function testItDisconnectDoesNothingWhenAccountNotFound(): void
    {
        $this->paymentAccount
            ->shouldReceive('findByUserAndProvider')
            ->once()
            ->andReturn(null);

        $this->service->disconnect(1);

        $this->addToAssertionCount(1);
    }

    public function testItDisconnectResetsAccountFields(): void
    {
        $user = User::factory()->create();
        $account = PaymentAccount::query()->create([
            'user_id' => $user->id,
            'provider' => PaymentGatewayEnum::MERCADO_PAGO->value,
            'public_key' => 'public-key',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'mp_user_id' => 555,
            'scope' => 'read write',
            'token_expires_at' => now()->addHour(),
            'status' => PaymentAccountsStatusEnum::INTEGRATED->value,
        ]);

        $this->paymentAccount
            ->shouldReceive('findByUserAndProvider')
            ->once()
            ->andReturn($account);

        $this->service->disconnect($user->id);

        $this->assertDatabaseHas('payment_accounts', [
            'id' => $account->id,
            'access_token' => null,
            'refresh_token' => null,
            'mp_user_id' => null,
            'status' => PaymentAccountsStatusEnum::PENDING->value,
        ]);
    }

    public function testItGetValidAccessTokenReturnsCurrentTokenWhenNotExpired(): void
    {
        $account = new PaymentAccount([
            'access_token' => 'still-valid-token',
            'token_expires_at' => now()->addHour(),
        ]);

        $token = $this->service->getValidAccessToken($account);

        $this->assertSame('still-valid-token', $token);
    }

    public function testItGetValidAccessTokenThrowsAndMarksNeedsReauthWhenNoRefreshToken(): void
    {
        $account = $this->persistAccountForToken(accessToken: 'expired-token', refreshToken: null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('payments.account_needs_reauth'));

        try {
            $this->service->getValidAccessToken($account);
        } finally {
            $this->assertDatabaseHas('payment_accounts', [
                'id' => $account->id,
                'status' => PaymentAccountsStatusEnum::NEEDS_REAUTH->value,
            ]);
        }
    }

    public function testItGetValidAccessTokenRefreshesSuccessfully(): void
    {
        $account = $this->persistAccountForToken(accessToken: 'expired-token', refreshToken: 'refresh-token-1');

        $this->connectWrapper
            ->shouldReceive('refreshAccessToken')
            ->once()
            ->with('refresh-token-1')
            ->andReturn([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
                'user_id' => 555,
                'public_key' => null,
                'scope' => null,
            ]);

        $token = $this->service->getValidAccessToken($account);

        $this->assertSame('new-access-token', $token);
        $this->assertDatabaseHas('payment_accounts', [
            'id' => $account->id,
            'status' => PaymentAccountsStatusEnum::INTEGRATED->value,
        ]);
    }

    public function testItGetValidAccessTokenKeepsAccountIntegratedWhenRefreshFailsTransiently(): void
    {
        $account = $this->persistAccountForToken(accessToken: 'expired-token', refreshToken: 'refresh-token-1');

        $this->connectWrapper
            ->shouldReceive('refreshAccessToken')
            ->once()
            ->andThrow(new \RuntimeException('mercado pago indisponível'));

        try {
            $this->service->getValidAccessToken($account);
            $this->fail('Era esperada a exceção de falha transitória.');
        } catch (\RuntimeException $e) {
            $this->assertSame('mercado pago indisponível', $e->getMessage());
        }

        $this->assertDatabaseMissing('payment_accounts', [
            'id' => $account->id,
            'status' => PaymentAccountsStatusEnum::NEEDS_REAUTH->value,
        ]);
    }

    public function testItGetValidAccessTokenMarksNeedsReauthWhenRefreshIsRejected(): void
    {
        $account = $this->persistAccountForToken(accessToken: 'expired-token', refreshToken: 'refresh-token-1');

        $this->connectWrapper
            ->shouldReceive('refreshAccessToken')
            ->once()
            ->andThrow(new \DomainException('recusado'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('payments.account_needs_reauth'));

        try {
            $this->service->getValidAccessToken($account);
        } finally {
            $this->assertDatabaseHas('payment_accounts', [
                'id' => $account->id,
                'status' => PaymentAccountsStatusEnum::NEEDS_REAUTH->value,
            ]);
        }
    }

    private function captureAuthorizationState(int $userId): string
    {
        $capturedState = null;

        $this->connectWrapper
            ->shouldReceive('buildAuthorizationUrl')
            ->once()
            ->andReturnUsing(function (string $state) use (&$capturedState) {
                $capturedState = $state;

                return 'https://auth.mercadopago.com.br/authorization?state='.$state;
            });

        $this->service->buildAuthorizationUrl($userId);

        return $capturedState;
    }

    private function persistAccountForToken(string $accessToken, ?string $refreshToken): PaymentAccount
    {
        $user = User::factory()->create();

        return PaymentAccount::query()->create([
            'user_id' => $user->id,
            'provider' => PaymentGatewayEnum::MERCADO_PAGO->value,
            'public_key' => 'public-key',
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'mp_user_id' => 555,
            'scope' => 'read write',
            'token_expires_at' => now()->subMinute(),
            'status' => PaymentAccountsStatusEnum::INTEGRATED->value,
        ]);
    }
}
