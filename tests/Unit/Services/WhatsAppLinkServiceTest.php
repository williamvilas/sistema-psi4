<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\WhatsAppLinkService;
use Tests\TestCase;

class WhatsAppLinkServiceTest extends TestCase
{
    private WhatsAppLinkService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new WhatsAppLinkService;
    }

    public function testItBuildBirthdayMessageWithFirstName(): void
    {
        $message = $this->service->birthdayMessage('Ana Maria Souza');

        $this->assertSame(__('patients.whatsapp_birthday_message', ['name' => 'Ana']), $message);
    }

    public function testItBuildBirthdayMessageWithSingleName(): void
    {
        $this->assertStringStartsWith('Oi, Joana!', $this->service->birthdayMessage('Joana'));
    }

    public function testItBuildBirthdayMessageTrimmingAndCapitalizingName(): void
    {
        $this->assertStringStartsWith('Oi, Pedro!', $this->service->birthdayMessage('  pedro henrique  '));
    }

    public function testItBuildBirthdayMessageWithoutTherapyReferences(): void
    {
        $message = mb_strtolower($this->service->birthdayMessage('Ana'));

        foreach (['sessão', 'terapia', 'psicólog', 'consulta'] as $term) {
            $this->assertStringNotContainsString($term, $message);
        }
    }
}
