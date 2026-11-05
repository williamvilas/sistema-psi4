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
        // O agendamento self-service pode criar pacientes distintos com o mesmo telefone (ex.: pai e filho)
        Schema::table('patients', function (Blueprint $table) {
            $table->index(['user_id', 'phone'], 'patients_user_id_phone_index');
            $table->dropUnique('patients_user_id_active_phone_unique');
        });

        DB::statement('ALTER TABLE patients DROP COLUMN active_phone');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE patients ADD COLUMN active_phone CHAR(12) GENERATED ALWAYS AS (IF(deleted_at IS NULL, phone, NULL)) VIRTUAL');

        Schema::table('patients', function (Blueprint $table) {
            $table->unique(['user_id', 'active_phone'], 'patients_user_id_active_phone_unique');
            $table->dropIndex('patients_user_id_phone_index');
        });
    }
};
