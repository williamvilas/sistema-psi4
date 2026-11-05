<?php

declare(strict_types=1);

use App\Enums\AccessLogActionEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Trilha de auditoria de acessos (LGPD art. 37): logins e operações sobre prontuários.
 * Somente inserção; medical_record_id fica sem FK para o registro sobreviver à exclusão do prontuário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('action', AccessLogActionEnum::values());
            $table->unsignedBigInteger('medical_record_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'medical_record_id']);
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_logs');
    }
};
