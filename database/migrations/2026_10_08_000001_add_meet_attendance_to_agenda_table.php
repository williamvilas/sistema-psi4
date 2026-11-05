<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda', function (Blueprint $table) {
            $table->string('google_meet_code', 20)->nullable()->after('google_synced_at')->index();
            $table->string('attendance_source', 20)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('agenda', function (Blueprint $table) {
            $table->dropIndex(['google_meet_code']);
            $table->dropColumn(['google_meet_code', 'attendance_source']);
        });
    }
};
