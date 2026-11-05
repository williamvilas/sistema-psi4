<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class LandingPageTest extends TestCase
{
    public function testItShowsMenuSections(): void
    {
        $this->get(route('page-main'))
            ->assertOk()
            ->assertSee('id="como-funciona"', false)
            ->assertSee('id="precos"', false)
            ->assertSee('id="duvidas"', false);
    }

    public function testItDoesNotShowRemovedSections(): void
    {
        $this->get(route('page-main'))
            ->assertOk()
            ->assertDontSee('id="time-calculator"', false)
            ->assertDontSee('id="receita-saude"', false)
            ->assertDontSee('class="lp-scenes"', false)
            ->assertSee('landing_page_v5', false);
    }

    public function testItShowsVideoSection(): void
    {
        $this->get(route('page-main'))
            ->assertOk()
            ->assertSee('id="videochamada"', false)
            ->assertSee('imgs/landing-page/sala-video-03.webp', false);
    }

    // O vídeo passa pelo servidor (JVB): a landing e a página de segurança não podem chamá-lo de ponta a ponta
    public function testItDoesNotClaimEndToEndVideoCall(): void
    {
        $this->get(route('page-main'))
            ->assertOk()
            ->assertDontSee('chamada é cifrada de ponta a ponta')
            ->assertDontSee('cifrada de ponta a ponta, sem gravação');

        $this->get(route('security'))
            ->assertOk()
            ->assertDontSee('com <strong>criptografia de ponta a ponta</strong> na chamada', false)
            ->assertSee('não é cifrada de ponta a ponta', false);
    }

    public function testItShowsSessionActivitiesFromCatalog(): void
    {
        $response = $this->get(route('page-main'))
            ->assertOk()
            ->assertSee('id="durante-a-sessao"', false)
            ->assertSee('Não é teste psicológico')
            ->assertSee('Como funcionam as dinâmicas na sessão online?')
            ->assertSee('href="#durante-a-sessao"', false);

        foreach (config('session_activities.activities') as $activity) {
            $response->assertSee($activity['title']);
        }
    }

    // Desligada (PSI_TRANSCRIPTION_ENABLED), a landing não anuncia a transcrição
    public function testItHidesTranscriptionWhenDisabled(): void
    {
        config(['session_transcription.enabled' => false]);

        $this->get(route('page-main'))
            ->assertOk()
            ->assertDontSee('Rascunho do registro')
            ->assertDontSee('A sessão é gravada?')
            ->assertDontSee('Transcrição da sessão com rascunho do registro');
    }

    // Ligada, a landing não pode dizer que nada é gravado e mostra a cota real de cada plano
    public function testItShowsTranscriptionWhenEnabled(): void
    {
        config([
            'session_transcription.enabled' => true,
            'plans.plans.free.total_transcription_minutes' => 120,
            'plans.plans.professional.total_transcription_minutes' => 600,
        ]);

        $this->get(route('page-main'))
            ->assertOk()
            ->assertSee('Rascunho do registro')
            ->assertSee('A sessão é gravada?')
            ->assertSee('menores de 18 anos')
            ->assertSee('Transcrição da sessão com rascunho do registro: 120 minutos por mês')
            ->assertSee('Transcrição da sessão com rascunho do registro: 600 minutos por mês')
            ->assertDontSee('Nada é gravado')
            ->assertDontSee('O áudio e o vídeo trafegam mas não ficam guardados');
    }
}
