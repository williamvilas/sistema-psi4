<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Convites de uso único para criar conta. O token só é localizado pelo hash (SHA-256);
 * o valor em claro existe apenas no link entregue à psicóloga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_invitations', function (Blueprint $table) {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->unsignedBigInteger('lead_id')->nullable();
            // Origem do convite (ex.: supervisora parceira), para medir indicação.
            $table->string('source', 100)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('used_by_user_id')->nullable();
            $table->timestamps();
            $table->foreign('lead_id')->references('id')->on('registration_leads')->nullOnDelete();
            $table->foreign('used_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_invitations');
    }
};
