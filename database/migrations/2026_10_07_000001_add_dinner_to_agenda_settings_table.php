<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_settings', function (Blueprint $table) {
            $table->boolean('dinner_enabled')->default(false)->after('lunch_end');
            $table->time('dinner_start')->nullable()->after('dinner_enabled');
            $table->time('dinner_end')->nullable()->after('dinner_start');
        });
    }

    public function down(): void
    {
        Schema::table('agenda_settings', function (Blueprint $table) {
            $table->dropColumn(['dinner_enabled', 'dinner_start', 'dinner_end']);
        });
    }
};
