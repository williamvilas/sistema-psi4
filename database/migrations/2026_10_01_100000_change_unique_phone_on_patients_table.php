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
        // Coluna gerada: só tem valor para pacientes não apagados, assim o unique
        // (user_id, phone) vale apenas para registros ativos (NULL não colide no unique)
        DB::statement('ALTER TABLE patients ADD COLUMN active_phone CHAR(12) GENERATED ALWAYS AS (IF(deleted_at IS NULL, phone, NULL)) VIRTUAL');

        Schema::table('patients', function (Blueprint $table) {
            $table->unique(['user_id', 'active_phone'], 'patients_user_id_active_phone_unique');
            // O índice antigo atende à FK de user_id, então a nova unique precisa existir antes de removê-lo
            $table->dropUnique(['user_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->unique(['user_id', 'phone']);
            $table->dropUnique('patients_user_id_active_phone_unique');
        });

        DB::statement('ALTER TABLE patients DROP COLUMN active_phone');
    }
};
