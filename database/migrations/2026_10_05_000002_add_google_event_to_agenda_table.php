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
            $table->string('google_event_id', 100)->nullable()->after('room_token')->index();
            $table->timestamp('google_synced_at')->nullable()->after('google_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('agenda', function (Blueprint $table) {
            $table->dropIndex(['google_event_id']);
            $table->dropColumn(['google_event_id', 'google_synced_at']);
        });
    }
};
