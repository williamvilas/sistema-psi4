<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Helpers\UserHelper;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'code' => UserHelper::generateCode(),
            'timezone' => 'America/Sao_Paulo',
            // Obrigatório após o onboarding: é por ele que a psicóloga entra nas salas de vídeo
            'document' => '52998224725',
            'whatsapp' => '1198'.fake()->numerify('#######'),
            // Assinante por padrão: o plano Grátis é só consulta e barraria a maioria dos fluxos testados
            'current_plan' => 'professional',
            'onboarding_completed_at' => now(),
        ];
    }

    public function onboardingPending(): static
    {
        return $this->state(fn (array $attributes) => [
            'onboarding_completed_at' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
