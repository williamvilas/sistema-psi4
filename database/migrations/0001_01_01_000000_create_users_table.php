<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('two_factor')->index()->default(0);
            $table->char('google_id')->nullable()->index();
            $table->string('name');
            $table->string('email')->unique();
            $table->char('document', 14)->index()->nullable();
            $table->char('whatsapp', 11)->index()->nullable();
            $table->char('crp', 20)->index()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('timezone')->default('America/Sao_Paulo');
            $table->timestamp('last_login')->index()->nullable();
            $table->enum('current_plan', ['free', 'intermediary', 'advanced'])->default('free')->index();
            $table->char('code', 10)->index();
            $table->tinyInteger('force_change_password')->default(0)->index();
            $table->dateTime('temporary_password_expiration')->index()->nullable();
            $table->string('photo', 244)->nullable();
            $table->timestamp('demo_expiration_in')->index()->nullable();
            $table->timestamp('plan_expiration_date')->index()->nullable();
            $table->timestamp('plan_activated_on')->index()->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
