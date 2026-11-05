<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * O consumo de IA passou para `user_feature_usages` (feature `ai_tokens`), com cota mensal por plano.
 * Rodar só depois do deploy do microserviço de integrações que deixou de ler esta tabela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ai_token_balances');
    }

    public function down(): void
    {
        Schema::create('ai_token_balances', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_uuid')->unique();
            $table->unsignedBigInteger('tokens_available')->default(0);
            $table->unsignedBigInteger('tokens_used')->default(0);
            $table->timestamps();
            $table->foreign('user_uuid')->references('uuid')->on('users')->cascadeOnDelete();
        });
    }
};
