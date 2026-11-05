<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserTest extends TestCase
{
    use DatabaseTransactions;

    public function testItCreateUserGeneratesUuidSlug(): void
    {
        // Act
        $user = User::factory()->create();

        // Assert
        $this->assertTrue(Str::isUuid((string) $user->slug));
        $this->assertNotSame($user->uuid, $user->slug);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'slug' => $user->slug]);
    }

    public function testItCreateUserKeepsGivenSlug(): void
    {
        // Arrange
        $slug = 'dra-teste-'.uniqid();

        // Act
        $user = User::factory()->create(['slug' => $slug]);

        // Assert
        $this->assertSame($slug, $user->slug);
    }

    public function testItCreateUsersGenerateDifferentSlugs(): void
    {
        // Act
        $first = User::factory()->create();
        $second = User::factory()->create();

        // Assert
        $this->assertNotSame($first->slug, $second->slug);
    }
}
