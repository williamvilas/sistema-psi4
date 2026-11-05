<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class WhatsAppContactTest extends TestCase
{
    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';
    private const DESKTOP = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36';

    public function testMobileRedirectsToApiWhatsapp(): void
    {
        config(['info.whatsapp' => '(12) 98884-0421']);

        $response = $this->withHeader('User-Agent', self::IPHONE)->get(route('whatsapp.contact'));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://api.whatsapp.com/send?1=pt_BR&phone=5512988840421', $response->headers->get('Location'));
    }

    public function testWebRedirectsToWebWhatsappWithMessage(): void
    {
        $response = $this->withHeader('User-Agent', self::DESKTOP)
            ->get(route('whatsapp.contact', ['message' => "Olá!\nPreciso de ajuda"]));

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://web.whatsapp.com/send?', $location);
        $this->assertStringContainsString('&text='.rawurlencode("Olá!\nPreciso de ajuda"), $location);
    }

    public function testLegacyEncodedLineBreakIsNormalized(): void
    {
        $location = $this->withHeader('User-Agent', self::DESKTOP)
            ->get(route('whatsapp.contact', ['message' => 'Oi%0ATudo bem']))
            ->headers->get('Location');

        $this->assertStringContainsString('&text=Oi%0ATudo%20bem', $location);
    }
}
