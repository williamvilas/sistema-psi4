<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SettingsAgendaTabsTest extends TestCase
{
    use DatabaseTransactions;

    public function testItGroupsAgendaSectionsInsideSideMenu(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        $html = $this->actingAs($user)->get(route('settings.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="settingsNav"', $html);
        $this->assertStringContainsString('id="agendaTabs"', $html);

        foreach (['horarios-tab', 'feriados-tab', 'agendamento-online-tab', 'google-calendar-tab', 'psicologo-parent-tab', 'pagamentos-parent-tab', 'seguranca-parent-tab'] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html);
        }
    }

    public function testItNoLongerShowsBookingAndGoogleAsTopLevelMenus(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        $html = $this->actingAs($user)->get(route('settings.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('agendamento-online-parent', $html);
        $this->assertStringNotContainsString('google-calendar-parent', $html);
        $this->assertStringNotContainsString('agenda-parent-tab', $html);
    }

    public function testItShowsDurationAsOptionsWithDefaultChecked(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        $this->actingAs($user)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('id="consultationTime50" name="consultation_time" value="00:50" autocomplete="off" checked', false)
            ->assertSee('id="availableTimeMinutes10" name="available_time_minutes" value="00:10" autocomplete="off" checked', false)
            ->assertSee('>Sem intervalo</label>', false)
            ->assertDontSee('<select id="consultationTime"', false);
    }
}
