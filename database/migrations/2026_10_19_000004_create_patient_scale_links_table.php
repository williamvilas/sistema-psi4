<?php

declare(strict_types=1);

use App\Enums\ScaleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Links públicos para o paciente responder uma escala sem login.
 * O token só é localizado pelo hash; a cópia cifrada (APP_KEY) permite à psicóloga copiar o link de novo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_scale_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('patient_id');
            $table->enum('scale', ScaleEnum::values());
            $table->char('token_hash', 64)->unique();
            $table->text('token');
            // Caminho reservado ao gerar a URL de upload; confirmado no envio das respostas.
            $table->string('content_path')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('scale_application_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'patient_id', 'scale', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('patient_id')->references('id')->on('patients');
            $table->foreign('scale_application_id')->references('id')->on('patient_scale_applications');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_scale_links');
    }
};
