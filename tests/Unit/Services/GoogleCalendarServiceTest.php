<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\GoogleCalendarStatusEnum;
use App\Helpers\CacheHelper;
use App\Models\GoogleCalendarAccount;
use App\Models\User;
use App\Services\GoogleCalendarService;
use App\Wrappers\GoogleCalendarWrapper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class GoogleCalendarServiceTest extends TestCase
{
    use DatabaseTransactions;

    private $wrapper;

    private GoogleCalendarService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['google-calendar.required_scopes' => ['calendar.events', 'calendar.calendarlist', 'freebusy']]);

        $this->wrapper = Mockery::mock(GoogleCalendarWrapper::class);
        $this->service = new GoogleCalendarService(new GoogleCalendarAccount, $this->wrapper);
        $this->user = User::factory()->create();
    }

    private function tokens(array $override = []): array
    {
        return array_merge([
            'access_token' => 'access-1',
            'refresh_token' => 'refresh-1',
            'expires_in' => 3600,
            'scope' => 'openid calendar.events calendar.calendarlist freebusy',
            'google_user_id' => 'g-1',
            'email' => 'psi@example.com',
        ], $override);
    }

    private function captureState(): string
    {
        $state = null;

        $this->wrapper->shouldReceive('buildAuthorizationUrl')->once()->andReturnUsing(function (string $s) use (&$state) {
            $state = $s;

            return 'https://accounts.google.com/auth';
        });

        $this->service->buildAuthorizationUrl($this->user->id);

        return $state;
    }

    private function account(array $override = []): GoogleCalendarAccount
    {
        return GoogleCalendarAccount::query()->create(array_merge([
            'user_id' => $this->user->id,
            'access_token' => 'access-1',
            'refresh_token' => 'refresh-1',
            'token_expires_at' => now()->addHour(),
            'status' => GoogleCalendarStatusEnum::INTEGRATED->value,
        ], $override));
    }

    public function testItConnectSuccessfullyCreatesAccount(): void
    {
        $state = $this->captureState();
        $this->wrapper->shouldReceive('exchangeAuthorizationCode')->with('code-1')->once()->andReturn($this->tokens());

        $this->service->connect('code-1', $state, $this->user->id);

        $account = GoogleCalendarAccount::query()->where('user_id', $this->user->id)->first();
        $this->assertSame(GoogleCalendarStatusEnum::INTEGRATED, $account->status);
        $this->assertSame('refresh-1', $account->refresh_token);
        $this->assertSame('psi@example.com', $account->email);
        $this->assertTrue($this->service->isConnected($this->user->id));
    }

    public function testItConnectResetsCalendarWhenGoogleAccountChanges(): void
    {
        $this->account(['google_user_id' => 'g-old', 'calendar_id' => 'outro@group.calendar.google.com']);
        $state = $this->captureState();
        $this->wrapper->shouldReceive('exchangeAuthorizationCode')->once()->andReturn($this->tokens());

        $this->service->connect('code-1', $state, $this->user->id);

        $this->assertSame('primary', $this->service->details($this->user->id)->calendar_id);
    }

    public function testItConnectThrowsExceptionWhenStateIsInvalid(): void
    {
        $this->wrapper->shouldReceive('exchangeAuthorizationCode')->never();

        $this->expectException(\DomainException::class);

        $this->service->connect('code-1', 'state-inexistente', $this->user->id);
    }

    public function testItConnectThrowsExceptionWhenStateBelongsToAnotherUser(): void
    {
        CacheHelper::put('gc_oauth_state:abc', $this->user->id + 999, now()->addMinute());
        $this->wrapper->shouldReceive('exchangeAuthorizationCode')->never();

        $this->expectException(\DomainException::class);

        $this->service->connect('code-1', 'abc', $this->user->id);
    }

    public function testItConnectThrowsScopeMissingAndRevokesWhenNoScopeWasGranted(): void
    {
        $state = $this->captureState();
        $this->wrapper->shouldReceive('exchangeAuthorizationCode')->once()->andReturn($this->tokens(['scope' => 'openid email']));
        $this->wrapper->shouldReceive('revokeToken')->once()->with('refresh-1');

        $this->expectException(\App\Exceptions\GoogleCalendarScopeMissingException::class);

        $this->service->connect('code-1', $state, $this->user->id);
    }

    public function testItConnectThrowsScopeMissingWhenOnlySomeScopesWereGranted(): void
    {
        $state = $this->captureState();
        $this->wrapper->shouldReceive('exchangeAuthorizationCode')->once()
            ->andReturn($this->tokens(['scope' => 'openid email calendar.events calendar.calendarlist']));
        $this->wrapper->shouldReceive('revokeToken')->once()->with('refresh-1');

        $this->expectException(\App\Exceptions\GoogleCalendarScopeMissingException::class);

        $this->service->connect('code-1', $state, $this->user->id);
    }

    public function testItConnectThrowsExceptionWhenRefreshTokenIsMissing(): void
    {
        $state = $this->captureState();
        $this->wrapper->shouldReceive('exchangeAuthorizationCode')->once()->andReturn($this->tokens(['refresh_token' => null]));

        $this->expectException(\DomainException::class);

        $this->service->connect('code-1', $state, $this->user->id);

        $this->assertFalse($this->service->isConnected($this->user->id));
    }

    public function testItDisconnectRevokesAndClearsTokens(): void
    {
        $account = $this->account();
        $this->wrapper->shouldReceive('revokeToken')->with('refresh-1')->once();

        $this->service->disconnect($this->user->id);

        $account->refresh();
        $this->assertNull($account->refresh_token);
        $this->assertSame(GoogleCalendarStatusEnum::PENDING, $account->status);
        $this->assertFalse($this->service->isConnected($this->user->id));
    }

    public function testItDisconnectDoesNothingWithoutAccount(): void
    {
        $this->wrapper->shouldReceive('revokeToken')->never();

        $this->service->disconnect($this->user->id);

        $this->assertNull($this->service->details($this->user->id));
    }

    public function testItGetValidAccessTokenReturnsCurrentTokenWhenNotExpired(): void
    {
        $account = $this->account();
        $this->wrapper->shouldReceive('refreshAccessToken')->never();

        $this->assertSame('access-1', $this->service->getValidAccessToken($account));
    }

    public function testItGetValidAccessTokenRefreshesExpiredToken(): void
    {
        $account = $this->account(['token_expires_at' => now()->subMinute()]);
        $this->wrapper->shouldReceive('refreshAccessToken')->with('refresh-1')->once()
            ->andReturn($this->tokens(['access_token' => 'access-2']));

        $this->assertSame('access-2', $this->service->getValidAccessToken($account));
        $this->assertSame('access-2', $account->fresh()->access_token);
    }

    public function testItGetValidAccessTokenMarksNeedsReauthWhenGoogleRefusesRefresh(): void
    {
        $account = $this->account(['token_expires_at' => now()->subMinute()]);
        $this->wrapper->shouldReceive('refreshAccessToken')->once()->andThrow(new \DomainException('invalid_grant'));

        try {
            $this->service->getValidAccessToken($account);
            $this->fail('Deveria lançar DomainException.');
        } catch (\DomainException) {
            $this->assertSame(GoogleCalendarStatusEnum::NEEDS_REAUTH, $account->fresh()->status);
            $this->assertFalse($this->service->isConnected($this->user->id));
        }
    }

    public function testItGetValidAccessTokenRethrowsTransientFailureWithoutInvalidatingAccount(): void
    {
        $account = $this->account(['token_expires_at' => now()->subMinute()]);
        $this->wrapper->shouldReceive('refreshAccessToken')->once()->andThrow(new \RuntimeException('timeout'));

        try {
            $this->service->getValidAccessToken($account);
            $this->fail('Deveria lançar RuntimeException.');
        } catch (\RuntimeException) {
            $this->assertSame(GoogleCalendarStatusEnum::INTEGRATED, $account->fresh()->status);
        }
    }

    public function testItUpdatePreferencesSavesCalendarAndInvite(): void
    {
        $this->account();
        $this->wrapper->shouldReceive('listWritableCalendars')->once()->andReturn([
            ['id' => 'primary', 'summary' => 'Principal', 'primary' => true],
            ['id' => 'consultas@group.calendar.google.com', 'summary' => 'Consultas', 'primary' => false],
        ]);

        $this->service->updatePreferences($this->user->id, 'consultas@group.calendar.google.com', true);

        $account = $this->service->details($this->user->id);
        $this->assertSame('consultas@group.calendar.google.com', $account->calendar_id);
        $this->assertTrue($account->invite_patient);
    }

    public function testItUpdatePreferencesThrowsExceptionWhenCalendarIsNotWritable(): void
    {
        $this->account();
        $this->wrapper->shouldReceive('listWritableCalendars')->once()->andReturn([
            ['id' => 'primary', 'summary' => 'Principal', 'primary' => true],
        ]);

        $this->expectException(\DomainException::class);

        $this->service->updatePreferences($this->user->id, 'alheio@group.calendar.google.com', false);
    }

    public function testItCalendarsThrowsExceptionWhenNotConnected(): void
    {
        $this->expectException(\DomainException::class);

        $this->service->calendars($this->user->id);
    }

    private function busyAccount(array $override = []): GoogleCalendarAccount
    {
        return $this->account(array_merge([
            'scope' => 'openid calendar.events freebusy',
            'block_busy' => true,
            'calendar_id' => 'consultas@group.calendar.google.com',
        ], $override));
    }

    public function testItBusyIntervalsReturnsEmptyWhenBlockingIsDisabled(): void
    {
        config(['google-calendar.freebusy_scope' => 'freebusy']);
        $this->busyAccount(['block_busy' => false]);
        $this->wrapper->shouldReceive('queryBusyIntervals')->never();

        $this->assertSame([], $this->service->busyIntervals($this->user->id, now(), now()->addDay()));
    }

    public function testItBusyIntervalsReturnsEmptyWhenFreeBusyScopeWasNotGranted(): void
    {
        config(['google-calendar.freebusy_scope' => 'freebusy']);
        $this->busyAccount(['scope' => 'openid calendar.events']);
        $this->wrapper->shouldReceive('queryBusyIntervals')->never();

        $this->assertSame([], $this->service->busyIntervals($this->user->id, now(), now()->addDay()));
    }

    public function testItBusyIntervalsQueriesPrimaryAndConsultationCalendarsAndCachesResult(): void
    {
        config(['google-calendar.freebusy_scope' => 'freebusy']);
        $this->busyAccount();
        $start = now()->startOfDay();
        $end = $start->copy()->addDay();

        $this->wrapper->shouldReceive('queryBusyIntervals')->once()
            ->withArgs(fn ($token, $ids) => $ids === ['primary', 'consultas@group.calendar.google.com'])
            ->andReturn([['start' => '2030-10-07T12:00:00Z', 'end' => '2030-10-07T13:00:00Z']]);

        $first = $this->service->busyIntervals($this->user->id, $start, $end);
        $second = $this->service->busyIntervals($this->user->id, $start, $end);

        $this->assertCount(1, $first);
        $this->assertSame('2030-10-07 12:00:00', $first[0]['start']->format('Y-m-d H:i:s'));
        $this->assertSame($first[0]['start']->timestamp, $second[0]['start']->timestamp);
    }

    public function testItBusyIntervalsFailsOpenWhenGoogleFails(): void
    {
        config(['google-calendar.freebusy_scope' => 'freebusy']);
        $this->busyAccount();
        $this->wrapper->shouldReceive('queryBusyIntervals')->once()->andThrow(new \RuntimeException('timeout'));

        $this->assertSame([], $this->service->busyIntervals($this->user->id, now()->addYear(), now()->addYear()->addDay()));
    }

    public function testItUpdatePreferencesThrowsExceptionWhenBlockingWithoutFreeBusyScope(): void
    {
        config(['google-calendar.freebusy_scope' => 'freebusy']);
        $this->account(['scope' => 'openid calendar.events']);
        $this->wrapper->shouldReceive('listWritableCalendars')->never();

        $this->expectException(\DomainException::class);

        $this->service->updatePreferences($this->user->id, 'primary', false, true);
    }

    public function testItUpdatePreferencesSavesBlockBusyWhenScopeWasGranted(): void
    {
        config(['google-calendar.freebusy_scope' => 'freebusy']);
        $this->busyAccount(['block_busy' => false, 'calendar_id' => 'primary']);
        $this->wrapper->shouldReceive('listWritableCalendars')->once()->andReturn([['id' => 'primary', 'summary' => 'P', 'primary' => true]]);

        $this->service->updatePreferences($this->user->id, 'primary', false, true);

        $this->assertTrue($this->service->details($this->user->id)->block_busy);
    }
}
