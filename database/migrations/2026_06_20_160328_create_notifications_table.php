<?php

declare(strict_types=1);

use App\Enums\NotificationsStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            $table->string('type', 20)->index();
            $table->string('status', 20)->index()->default(NotificationsStatusEnum::PENDING->value);
            $table->text('message');
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes()->index();
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('patient_id')->references('id')->on('patients');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
