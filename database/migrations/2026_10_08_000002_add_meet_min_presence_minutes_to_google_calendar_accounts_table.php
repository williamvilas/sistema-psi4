<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_calendar_accounts', function (Blueprint $table) {
            $table->unsignedSmallInteger('meet_min_presence_minutes')->default(10)->after('block_busy');
        });
    }

    public function down(): void
    {
        Schema::table('google_calendar_accounts', function (Blueprint $table) {
            $table->dropColumn('meet_min_presence_minutes');
        });
    }
};
