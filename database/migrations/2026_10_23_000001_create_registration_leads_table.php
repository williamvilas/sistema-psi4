<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Pedidos de convite: quem tenta se cadastrar sem convite deixa Nome e WhatsApp
 * para que a equipe entre em contato e acompanhe a chegada de perto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Somente dígitos (DDD + número).
            $table->string('whatsapp', 20)->unique();
            $table->enum('status', ['new', 'contacted', 'invited', 'registered'])->default('new');
            $table->timestamp('consent_at');
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_leads');
    }
};
