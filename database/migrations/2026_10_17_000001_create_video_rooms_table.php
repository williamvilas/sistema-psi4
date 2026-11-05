<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_rooms', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Id curto público usado na URL do Jitsi (minúsculo)
            $table->string('room', 32)->unique();
            $table->string('session_id')->nullable()->index();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 16);
            $table->timestamps();
        });

        Schema::create('video_room_participants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('video_room_id')->constrained('video_rooms')->cascadeOnDelete();
            // HMAC-SHA256 do telefone em E.164: o telefone em claro nunca é persistido
            $table->char('phone_hmac', 64);
            $table->string('name', 120);
            $table->string('role', 16);
            $table->timestamps();

            $table->unique(['video_room_id', 'phone_hmac']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_room_participants');
        Schema::dropIfExists('video_rooms');
    }
};
