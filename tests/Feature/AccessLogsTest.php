<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AccessLogs;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AccessLogsTest extends TestCase
{
    use DatabaseTransactions;

    public function testItRegistersLoginInAccessLog(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        Auth::login($user);

        $this->assertTrue(AccessLogs::query()->where('user_id', $user->id)->where('action', 'login')->exists());
    }

    public function testItListsOnlyOwnLogs(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);
        $other = User::factory()->create(['current_plan' => 'professional']);

        AccessLogs::query()->insert([
            ['user_id' => $user->id, 'action' => 'login', 'ip' => '10.0.0.1', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0', 'created_at' => now()],
            ['user_id' => $other->id, 'action' => 'login', 'ip' => '10.9.9.9', 'user_agent' => null, 'created_at' => now()],
        ]);

        $html = $this->actingAs($user)->getJson(route('access-logs.paginate'))->assertOk()->json('html');

        $this->assertStringContainsString('10.0.0.1', $html);
        $this->assertStringContainsString('Chrome no Windows', $html);
        $this->assertStringNotContainsString('10.9.9.9', $html);
    }

    public function testItFiltersByAction(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        AccessLogs::query()->insert([
            ['user_id' => $user->id, 'action' => 'login', 'medical_record_id' => null, 'ip' => '10.0.0.1', 'created_at' => now()],
            ['user_id' => $user->id, 'action' => 'medical_record_viewed', 'medical_record_id' => 999999, 'ip' => '10.0.0.2', 'created_at' => now()],
        ]);

        $html = $this->actingAs($user)->getJson(route('access-logs.paginate', ['action' => 'medical_record_viewed']))->assertOk()->json('html');

        $this->assertStringContainsString('10.0.0.2', $html);
        $this->assertStringContainsString('Prontuário removido definitivamente', $html);
        $this->assertStringNotContainsString('10.0.0.1', $html);
    }

    public function testItListsOnlyRecordLogsInPanel(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);
        $other = User::factory()->create(['current_plan' => 'professional']);

        AccessLogs::query()->insert([
            ['user_id' => $user->id, 'action' => 'medical_record_viewed', 'medical_record_id' => 999999, 'ip' => '10.0.0.1', 'created_at' => now()],
            ['user_id' => $user->id, 'action' => 'medical_record_viewed', 'medical_record_id' => 999998, 'ip' => '10.0.0.2', 'created_at' => now()],
            ['user_id' => $user->id, 'action' => 'login', 'medical_record_id' => null, 'ip' => '10.0.0.3', 'created_at' => now()],
            ['user_id' => $other->id, 'action' => 'medical_record_viewed', 'medical_record_id' => 999999, 'ip' => '10.9.9.9', 'created_at' => now()],
        ]);

        $html = $this->actingAs($user)
            ->getJson(route('access-logs.paginate', ['panel' => 1, 'medical_record_id' => encrypt(999999)]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('data-access-logs-panel', $html);
        $this->assertStringContainsString('10.0.0.1', $html);
        $this->assertStringNotContainsString('10.0.0.2', $html);
        $this->assertStringNotContainsString('10.0.0.3', $html);
        $this->assertStringNotContainsString('10.9.9.9', $html);
    }

    public function testItListsNothingInPanelWithoutValidRecord(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        AccessLogs::query()->insert([
            ['user_id' => $user->id, 'action' => 'login', 'medical_record_id' => null, 'ip' => '10.0.0.1', 'created_at' => now()],
        ]);

        $html = $this->actingAs($user)
            ->getJson(route('access-logs.paginate', ['panel' => 1, 'medical_record_id' => 'invalido']))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('Nenhuma atividade encontrada', $html);
        $this->assertStringNotContainsString('10.0.0.1', $html);
    }

    public function testItFiltersPanelByActionAndPeriod(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional', 'timezone' => 'UTC']);

        AccessLogs::query()->insert([
            ['user_id' => $user->id, 'action' => 'medical_record_viewed', 'medical_record_id' => 999999, 'ip' => '10.0.0.1', 'created_at' => now()],
            ['user_id' => $user->id, 'action' => 'medical_record_updated', 'medical_record_id' => 999999, 'ip' => '10.0.0.2', 'created_at' => now()],
            ['user_id' => $user->id, 'action' => 'medical_record_viewed', 'medical_record_id' => 999999, 'ip' => '10.0.0.3', 'created_at' => now()->subMonths(2)],
        ]);

        $today = now()->format('d/m/Y');
        $html = $this->actingAs($user)
            ->getJson(route('access-logs.paginate', [
                'panel' => 1,
                'medical_record_id' => encrypt(999999),
                'action' => 'medical_record_viewed',
                'date' => $today.' - '.$today,
            ]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('10.0.0.1', $html);
        $this->assertStringNotContainsString('10.0.0.2', $html);
        $this->assertStringNotContainsString('10.0.0.3', $html);
    }

    public function testItPaginatesPanelWithOwnLinks(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        AccessLogs::query()->insert(array_map(fn (int $i) => [
            'user_id' => $user->id,
            'action' => 'medical_record_viewed',
            'medical_record_id' => 999999,
            'ip' => '10.0.0.'.$i,
            'created_at' => now()->subMinutes($i),
        ], range(1, config('app.paginate') + 1)));

        $html = $this->actingAs($user)
            ->getJson(route('access-logs.paginate', ['panel' => 1, 'medical_record_id' => encrypt(999999)]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('Página 1 de 2', $html);
        $this->assertStringContainsString('js-page-link', $html);
        $this->assertStringContainsString('panel=1', $html);
    }

    public function testItRendersIndex(): void
    {
        $user = User::factory()->create(['current_plan' => 'professional']);

        $this->actingAs($user)->get(route('access-logs.index'))
            ->assertOk()
            ->assertSee('Atividade de acesso');
    }

    public function testItRendersPublicSecurityPage(): void
    {
        $this->get(route('security'))
            ->assertOk()
            ->assertSee('Criptografia de ponta a ponta dos prontuários');
    }
}
