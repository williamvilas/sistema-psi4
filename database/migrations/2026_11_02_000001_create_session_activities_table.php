<?php

declare(strict_types=1);

use App\Enums\SessionActivityEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_activities', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('agenda_id')->nullable();
            $table->unsignedBigInteger('medical_record_id');
            $table->enum('activity', SessionActivityEnum::values());
            $table->date('occurred_on');
            // Original imutável, cifrado no navegador com a chave pública (o prontuário pode ser editado depois).
            $table->string('content_path');
            $table->timestamps();
            $table->index(['user_id', 'patient_id', 'activity', 'occurred_on']);
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('agenda_id')->references('id')->on('agenda')->nullOnDelete();
            $table->foreign('medical_record_id')->references('id')->on('medical_records')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_activities');
    }
};
