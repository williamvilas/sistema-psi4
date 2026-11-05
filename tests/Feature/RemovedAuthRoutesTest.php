<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RemovedAuthRoutesTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Telas do Breeze/Inertia que nunca existiram: antes davam 500 para quem estava logado.
     */
    public function testItReturnsNotFoundForRemovedBreezeScreens(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/confirm-password')->assertNotFound();
        $this->actingAs($user)->get('/verify-email')->assertNotFound();
        $this->actingAs($user)->post('/email/verification-notification')->assertNotFound();
    }
}
