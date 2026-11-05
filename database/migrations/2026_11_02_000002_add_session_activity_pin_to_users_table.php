<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('session_activity_pin')->nullable();
            $table->unsignedTinyInteger('session_activity_pin_attempts')->default(0);
            $table->timestamp('session_activity_pin_locked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['session_activity_pin', 'session_activity_pin_attempts', 'session_activity_pin_locked_at']);
        });
    }
};
