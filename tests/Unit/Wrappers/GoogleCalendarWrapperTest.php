<?php

declare(strict_types=1);

namespace Tests\Unit\Wrappers;

use App\Jobs\RegisterLog;
use App\Wrappers\GoogleCalendarWrapper;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarWrapperTest extends TestCase
{
    private GoogleCalendarWrapper $wrapper;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'google-calendar.oauth' => ['client_id' => 'cid', 'client_secret' => 'secret', 'redirect_uri' => 'https://app.test/cb'],
            'google-calendar.scopes' => ['openid', 'calendar.events'],
        ]);

        Bus::fake();

        $this->wrapper = new GoogleCalendarWrapper;
    }

    private function idToken(array $claims): string
    {
        return 'h.'.rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=').'.s';
    }

    public function testItBuildAuthorizationUrlRequestsOfflineAccess(): void
    {
        $url = $this->wrapper->buildAuthorizationUrl('state-1');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
        $this->assertStringContainsString('state=state-1', $url);
        $this->assertStringContainsString('scope=openid+calendar.events', $url);
    }

    public function testItExchangeAuthorizationCodeParsesTokensAndIdToken(): void
    {
        Http::fake(['oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 3599, 'scope' => 'openid calendar.events',
            'id_token' => $this->idToken(['sub' => '123', 'email' => 'psi@example.com']),
        ])]);

        $tokens = $this->wrapper->exchangeAuthorizationCode('code-1');

        $this->assertSame('a', $tokens['access_token']);
        $this->assertSame('r', $tokens['refresh_token']);
        $this->assertSame('123', $tokens['google_user_id']);
        $this->assertSame('psi@example.com', $tokens['email']);
        Bus::assertNotDispatched(RegisterLog::class);
    }

    public function testItRefreshAccessTokenThrowsDomainExceptionAndLogsMaskedCurlOnClientError(): void
    {
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        try {
            $this->wrapper->refreshAccessToken('refresh-secreto');
            $this->fail('Deveria lançar DomainException.');
        } catch (\DomainException) {
            Bus::assertDispatchedSync(RegisterLog::class, function (RegisterLog $job) {
                $curl = $job->exception['data']['curl'];

                return $job->exception['data']['status'] === 400
                    && str_contains($curl, 'refresh_token=***')
                    && str_contains($curl, 'client_secret=***')
                    && ! str_contains($curl, 'refresh-secreto');
            });
        }
    }

    public function testItInsertEventWithoutMeetRequestAndMasksAuthorizationOnFailure(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['error' => 'boom'], 500)]);

        try {
            $this->wrapper->insertEvent('token-secreto', 'primary', ['id' => 'psi4a', 'summary' => 'Consulta – Ana'], false);
            $this->fail('Deveria lançar RequestException.');
        } catch (RequestException) {
            Http::assertSent(fn (Request $r) => ! isset($r['conferenceData'])
                && ! str_contains($r->url(), 'conferenceDataVersion')
                && str_contains($r->url(), 'sendUpdates=none'));

            Bus::assertDispatchedSync(RegisterLog::class, function (RegisterLog $job) {
                $curl = $job->exception['data']['curl'];

                return str_contains($curl, 'Bearer ***')
                    && ! str_contains($curl, 'token-secreto')
                    && ! str_contains($curl, 'Ana');
            });
        }
    }

    public function testItInsertEventReturnsEventId(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['id' => 'psi4a', 'hangoutLink' => 'https://meet.google.com/abc'])]);

        $result = $this->wrapper->insertEvent('t', 'primary', ['id' => 'psi4a'], false);

        $this->assertSame(['id' => 'psi4a'], $result);
        Bus::assertNotDispatched(RegisterLog::class);
    }

    public function testItInsertEventReturnsNullWhenEventAlreadyExists(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response([], 409)]);

        $this->assertNull($this->wrapper->insertEvent('t', 'primary', ['id' => 'psi4a'], false));
        Bus::assertNotDispatched(RegisterLog::class);
    }

    public function testItPatchEventReturnsNullWhenEventIsGone(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response([], 404)]);

        $this->assertNull($this->wrapper->patchEvent('t', 'primary', 'psi4a', [], false));
    }

    public function testItDeleteEventIgnoresAlreadyDeletedEvent(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response([], 410)]);

        $this->wrapper->deleteEvent('t', 'primary', 'psi4a', false);

        Bus::assertNotDispatched(RegisterLog::class);
    }

    public function testItRateLimitedForbiddenIsRetryable(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['error' => ['errors' => [['reason' => 'rateLimitExceeded']]]], 403)]);

        $this->expectException(RequestException::class);

        $this->wrapper->deleteEvent('t', 'primary', 'psi4a', false);
    }

    public function testItListWritableCalendarsMapsItems(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['items' => [
            ['id' => 'primary', 'summary' => 'Principal', 'primary' => true],
            ['id' => 'x@group.calendar.google.com', 'summary' => 'Consultas', 'summaryOverride' => 'Minhas consultas'],
        ]])]);

        $calendars = $this->wrapper->listWritableCalendars('t');

        $this->assertSame('Principal', $calendars[0]['summary']);
        $this->assertSame('Minhas consultas', $calendars[1]['summary']);
        $this->assertFalse($calendars[1]['primary']);
    }

    public function testItQueryBusyIntervalsMergesCalendarsAndIgnoresErroredOnes(): void
    {
        Http::fake(['www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => [
            'primary' => ['busy' => [['start' => '2030-10-07T12:00:00Z', 'end' => '2030-10-07T13:00:00Z']]],
            'x@group.calendar.google.com' => ['busy' => [['start' => '2030-10-07T15:00:00Z', 'end' => '2030-10-07T16:00:00Z']]],
            'inexistente' => ['errors' => [['reason' => 'notFound']], 'busy' => []],
        ]])]);

        $busy = $this->wrapper->queryBusyIntervals('t', ['primary', 'x@group.calendar.google.com', 'inexistente'], Carbon::parse('2030-10-07'), Carbon::parse('2030-10-08'));

        $this->assertCount(2, $busy);
        $this->assertSame('2030-10-07T15:00:00Z', $busy[1]['start']);
        Http::assertSent(fn (Request $r) => $r['items'] === [['id' => 'primary'], ['id' => 'x@group.calendar.google.com'], ['id' => 'inexistente']]
            && $r['timeZone'] === 'UTC');
    }

    public function testItQueryBusyIntervalsLogsFailureWithoutRequestBody(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response([], 500)]);

        try {
            $this->wrapper->queryBusyIntervals('t', ['pessoal@example.com'], Carbon::parse('2030-10-07'), Carbon::parse('2030-10-08'));
            $this->fail('Deveria lançar RequestException.');
        } catch (RequestException) {
            Bus::assertDispatchedSync(RegisterLog::class, fn (RegisterLog $job) => ! str_contains($job->exception['data']['curl'], 'pessoal@example.com'));
        }
    }
}
