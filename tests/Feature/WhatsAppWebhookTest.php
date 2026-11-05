<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppWebhookJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        config(['whatsapp.app_secret' => 'segredo', 'whatsapp.verify_token' => 'token-verificacao']);
    }

    private function postSigned(string $body, ?string $signature = null)
    {
        $signature ??= 'sha256='.hash_hmac('sha256', $body, 'segredo');

        return $this->call('POST', '/api/webhook/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $body);
    }

    public function testItQueueWebhookWithValidSignature(): void
    {
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => '1']]]);

        $this->postSigned($body)->assertOk();

        Queue::assertPushed(ProcessWhatsAppWebhookJob::class, fn ($job) => $job->payload['entry'][0]['id'] === '1');
    }

    public function testItRejectWebhookWithInvalidSignature(): void
    {
        $this->postSigned('{"entry":[]}', 'sha256='.str_repeat('0', 64))->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function testItRejectWebhookWithoutSignature(): void
    {
        $this->postJson('/api/webhook/whatsapp', ['entry' => []])->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function testItAnswerVerificationChallenge(): void
    {
        $this->get('/api/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=token-verificacao&hub.challenge=12345')
            ->assertOk()
            ->assertSeeText('12345');
    }

    public function testItRejectVerificationWithWrongToken(): void
    {
        $this->get('/api/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=errado&hub.challenge=12345')
            ->assertForbidden();
    }
}
