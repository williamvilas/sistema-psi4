<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psychologist_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('clinic_name')->nullable();
            $table->string('cnpj', 18)->nullable();
            $table->string('crp', 30)->nullable();
            $table->string('cep', 9)->nullable();
            $table->string('bairro')->nullable();
            $table->string('rua')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento')->nullable();
            $table->string('photo_path')->nullable();
            $table->json('agenda_days')->nullable();
            $table->boolean('lunch_enabled')->default(true);
            $table->time('lunch_start')->nullable();
            $table->time('lunch_end')->nullable();
            $table->json('holidays')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psychologist_settings');
    }
};
