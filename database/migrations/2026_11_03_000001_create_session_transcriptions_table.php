<?php

declare(strict_types=1);

use App\Enums\SessionTranscriptionConsentEnum;
use App\Enums\SessionTranscriptionStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_transcriptions', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('agenda_id')->nullable();
            $table->unsignedBigInteger('video_room_id')->nullable();
            $table->unsignedBigInteger('medical_record_id')->nullable();
            $table->enum('status', SessionTranscriptionStatusEnum::values());
            $table->unsignedInteger('parts_count')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->date('occurred_on');
            $table->timestamp('adult_confirmed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('review_deadline')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'review_deadline']);
            $table->index(['video_room_id', 'status']);
            $table->index(['status', 'review_deadline']);
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('patient_id')->references('id')->on('patients')->cascadeOnDelete();
            $table->foreign('agenda_id')->references('id')->on('agenda')->nullOnDelete();
            $table->foreign('video_room_id')->references('id')->on('video_rooms')->nullOnDelete();
            $table->foreign('medical_record_id')->references('id')->on('medical_records')->nullOnDelete();
        });

        Schema::create('session_transcription_consents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_transcription_id');
            $table->char('participant_ref', 16);
            $table->enum('decision', SessionTranscriptionConsentEnum::values());
            $table->unsignedInteger('consent_version');
            $table->char('consent_hash', 64);
            $table->timestamp('decided_at');
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->unique(['session_transcription_id', 'participant_ref'], 'session_transcription_consents_participant_unique');
            $table->foreign('session_transcription_id', 'session_transcription_consents_transcription_fk')
                ->references('id')->on('session_transcriptions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_transcription_consents');
        Schema::dropIfExists('session_transcriptions');
    }
};
