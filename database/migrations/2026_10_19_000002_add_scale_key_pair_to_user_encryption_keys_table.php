<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Par de chaves ECDH P-256 das escalas respondidas pelo paciente por link.
 * A pública cifra as respostas no navegador do paciente; a privada fica embrulhada pela chave dos prontuários
 * (só quem desbloqueia os prontuários consegue decifrar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_encryption_keys', function (Blueprint $table) {
            $table->string('scale_public_key', 255)->nullable()->after('key_check');
            $table->string('scale_wrapped_private_key', 512)->nullable()->after('scale_public_key');
        });
    }

    public function down(): void
    {
        Schema::table('user_encryption_keys', function (Blueprint $table) {
            $table->dropColumn(['scale_public_key', 'scale_wrapped_private_key']);
        });
    }
};
