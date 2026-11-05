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
        // Links de sala do Jitsi (integração removida) ficam sem uso: o link passa a ser só o Meet ou o informado manualmente
        DB::table('agenda')->whereNotNull('room_token')->update(['room_link' => null]);

        Schema::table('agenda', function (Blueprint $table) {
            $table->dropUnique(['room_token']);
            $table->dropColumn('room_token');
        });
    }

    public function down(): void
    {
        Schema::table('agenda', function (Blueprint $table) {
            $table->string('room_token', 32)->nullable()->unique()->after('room_link');
        });
    }
};
