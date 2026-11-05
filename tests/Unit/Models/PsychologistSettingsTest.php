<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\PsychologistSettings;
use Tests\TestCase;

class PsychologistSettingsTest extends TestCase
{
    private function makeSettings(array $address): PsychologistSettings
    {
        return (new PsychologistSettings)->forceFill($address);
    }

    public function testItMapsUrlBuildsGoogleMapsRouteWithEncodedAddress(): void
    {
        $settings = $this->makeSettings([
            'rua' => 'Avenida São João',
            'numero' => '110',
            'bairro' => 'Vila Ema',
            'cidade' => 'São José dos Campos',
            'cep' => '12243000',
        ]);

        $this->assertSame(
            'https://www.google.com/maps/dir/?api=1&destination='.urlencode('Avenida São João, 110, Vila Ema, São José dos Campos, 12243000, Brasil'),
            $settings->mapsUrl()
        );
    }

    public function testItMapsUrlIgnoresComplementAndEmptyFields(): void
    {
        $settings = $this->makeSettings([
            'rua' => 'Rua das Flores',
            'numero' => '100',
            'cidade' => 'Campinas',
            'complemento' => 'Sala 12',
        ]);

        $url = $settings->mapsUrl();

        $this->assertStringEndsWith(urlencode('Rua das Flores, 100, Campinas, Brasil'), $url);
        $this->assertStringNotContainsString('Sala', urldecode($url));
    }

    public function testItMapsUrlReturnsNullWhenAddressIsIncomplete(): void
    {
        $this->assertNull($this->makeSettings(['rua' => 'Rua das Flores', 'cidade' => 'Campinas'])->mapsUrl());
        $this->assertNull($this->makeSettings(['numero' => '100', 'cidade' => 'Campinas'])->mapsUrl());
        $this->assertNull($this->makeSettings(['rua' => 'Rua das Flores', 'numero' => '100'])->mapsUrl());
    }
}
