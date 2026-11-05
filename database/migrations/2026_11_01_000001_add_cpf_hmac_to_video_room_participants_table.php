<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Os participantes guardados só têm o telefone, que deixa de valer para quem tem CPF: as salas precisam ser recriadas
        DB::table('video_room_participants')->delete();

        Schema::table('video_room_participants', function (Blueprint $table) {
            // HMAC-SHA256 do CPF: o CPF em claro nunca é gravado na sala
            $table->char('cpf_hmac', 64)->nullable()->after('video_room_id');
            // Só o paciente sem CPF entra pelo telefone
            $table->char('phone_hmac', 64)->nullable()->change();

            $table->unique(['video_room_id', 'cpf_hmac']);
        });
    }

    public function down(): void
    {
        DB::table('video_room_participants')->delete();

        Schema::table('video_room_participants', function (Blueprint $table) {
            $table->dropUnique(['video_room_id', 'cpf_hmac']);
            $table->dropColumn('cpf_hmac');
            $table->char('phone_hmac', 64)->nullable(false)->change();
        });
    }
};
