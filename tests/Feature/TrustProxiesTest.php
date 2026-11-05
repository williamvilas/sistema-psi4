<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustProxiesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/client-ip', fn (Request $request) => $request->ip());
    }

    public function testItUsesForwardedIpWhenRequestComesFromNginxContainer(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
            ->withHeader('X-Forwarded-For', '200.150.10.20')
            ->get('/_test/client-ip')
            ->assertOk()
            ->assertSee('200.150.10.20');
    }

    public function testItIgnoresForwardedIpWhenRequestComesFromUntrustedAddress(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '200.150.10.20'])
            ->withHeader('X-Forwarded-For', '10.0.0.1')
            ->get('/_test/client-ip')
            ->assertOk()
            ->assertSee('200.150.10.20')
            ->assertDontSee('10.0.0.1');
    }

    public function testItUsesForwardedSchemeWhenRequestComesFromNginxContainer(): void
    {
        Route::get('/_test/scheme', fn (Request $request) => $request->getScheme());

        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('/_test/scheme')
            ->assertOk()
            ->assertSee('https');
    }

    public function testItIgnoresForwardedHostWhenRequestComesFromNginxContainer(): void
    {
        Route::get('/_test/host', fn (Request $request) => $request->getHost());

        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
            ->withHeader('X-Forwarded-Host', 'atacante.com')
            ->get('/_test/host')
            ->assertOk()
            ->assertDontSee('atacante.com');
    }

    public function testItKeepsPasswordResetLinkOnAppHostWhenForwardedHostIsForged(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
            ->withHeaders(['X-Forwarded-Host' => 'atacante.com', 'X-Forwarded-Prefix' => '/atacante'])
            ->postJson(route('password.email'), ['email' => $user->email])
            ->assertOk();

        Mail::assertQueued(PasswordResetMail::class, fn (PasswordResetMail $mail) => ! str_contains($mail->url, 'atacante'));
    }
}
