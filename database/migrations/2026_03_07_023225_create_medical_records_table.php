<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agenda_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            $table->string('title')->index();
            // Valores fixos: o enum foi removido junto com a coluna (drop_evolution_from_medical_records_table).
            $table->enum('evolution', ['slight_improvement', 'significant_improvement', 'stable', 'slight_worsening', 'significant_worsening'])->index()->nullable();
            $table->date('date_of_consultation')->index();
            $table->string('notes_path');
            $table->timestamps();
            $table->softDeletes()->index();
            $table->foreign('agenda_id')->references('id')->on('agenda');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('patient_id')->references('id')->on('patients');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
