<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Presença pelo Google Meet removida: o tempo mínimo na sala não é mais usado
    public function up(): void
    {
        Schema::table('google_calendar_accounts', function (Blueprint $table) {
            $table->dropColumn('meet_min_presence_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('google_calendar_accounts', function (Blueprint $table) {
            $table->unsignedSmallInteger('meet_min_presence_minutes')->default(10)->after('block_busy');
        });
    }
};
