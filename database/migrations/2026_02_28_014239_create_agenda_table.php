<?php

declare(strict_types=1);

use App\Enums\AgendaStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            $table->string('type', 20)->index();
            $table->timestamp('scheduled_on')->index();
            $table->timestamp('ends_at')->index();
            $table->enum('status', AgendaStatusEnum::values())->index();
            $table->decimal('price', 10)->default(0);
            $table->string('room_link')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes()->index();
            $table->unique(['user_id', 'scheduled_on']);
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('patient_id')->references('id')->on('patients');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda');
    }
};
