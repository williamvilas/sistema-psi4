<?php

declare(strict_types=1);

use App\Enums\ScaleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_scale_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('patient_id');
            $table->enum('scale', ScaleEnum::values());
            $table->date('applied_on');
            // Respostas e pontuação cifradas no navegador (mesmo formato do conteúdo do prontuário).
            $table->string('content_path');
            $table->timestamps();
            $table->softDeletes()->index();
            $table->index(['user_id', 'patient_id', 'applied_on']);
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('patient_id')->references('id')->on('patients');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_scale_applications');
    }
};
