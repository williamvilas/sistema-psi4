<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Chave dos prontuários (criptografia de ponta a ponta).
 * O servidor guarda apenas a chave embrulhada pela senha mestre e pela chave de recuperação;
 * a senha mestre, a chave de recuperação e a chave em claro nunca saem do navegador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_encryption_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('passphrase_wrapped_key', 255);
            $table->string('passphrase_salt', 64);
            $table->unsignedInteger('passphrase_iterations');
            $table->string('recovery_wrapped_key', 255);
            $table->string('recovery_salt', 64);
            $table->unsignedInteger('recovery_iterations');
            $table->char('key_check', 64);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_encryption_keys');
    }
};
