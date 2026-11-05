<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class ProfileServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ProfileService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProfileService();
        $this->user = User::factory()->create(['document' => null, 'whatsapp' => '11999990000']);
        Auth::login($this->user);
    }

    public function testItUpdateSuccessfully(): void
    {
        // Arrange
        $payload = [
            'name' => 'Maria Souza',
            'document' => '52998224725',
            'whatsapp' => '11988887777',
            'email' => $this->user->email,
        ];

        // Act
        $user = $this->service->update($this->user, $payload);

        // Assert
        $user->refresh();
        $this->assertSame('Maria Souza', $user->name);
        $this->assertSame('52998224725', $user->document);
        $this->assertSame('11988887777', $user->whatsapp);
        $this->assertNotNull($user->email_verified_at);
    }

    public function testItUpdateKeepsDocumentWhenAlreadyFilled(): void
    {
        // Arrange
        $this->user->forceFill(['document' => '52998224725'])->save();

        // Act
        $user = $this->service->update($this->user, [
            'name' => $this->user->name,
            'document' => '11144477735',
            'whatsapp' => '11999990000',
            'email' => $this->user->email,
        ]);

        // Assert
        $this->assertSame('52998224725', $user->refresh()->document);
    }

    public function testItUpdateResetsEmailVerificationWhenEmailChanges(): void
    {
        // Act
        $user = $this->service->update($this->user, [
            'name' => $this->user->name,
            'document' => '',
            'whatsapp' => '11999990000',
            'email' => 'novo-email-perfil@example.com',
        ]);

        // Assert
        $user->refresh();
        $this->assertSame('novo-email-perfil@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function testItThrowExceptionWhenSaveFails(): void
    {
        // Arrange
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('save')->once()->andReturnFalse();

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(__('messages.failed_update'));

        // Act
        $this->service->update($user, ['name' => 'Maria Souza']);
    }
}
