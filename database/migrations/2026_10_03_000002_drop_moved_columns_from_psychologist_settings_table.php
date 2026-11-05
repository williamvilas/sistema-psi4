<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Executar somente depois de validar as tabelas agenda_settings, agenda_holidays e booking_settings em produção.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('psychologist_settings', function (Blueprint $table) {
            $table->dropIndex(['public_booking_enabled']);
            $table->dropColumn(['agenda_days', 'lunch_enabled', 'lunch_start', 'lunch_end', 'holidays', 'public_booking_enabled']);
        });
    }

    public function down(): void
    {
        Schema::table('psychologist_settings', function (Blueprint $table) {
            $table->json('agenda_days')->nullable();
            $table->boolean('lunch_enabled')->default(true);
            $table->time('lunch_start')->nullable();
            $table->time('lunch_end')->nullable();
            $table->json('holidays')->nullable();
            $table->boolean('public_booking_enabled')->default(false)->index();
        });
    }
};
