<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_settings', function (Blueprint $table) {
            $table->time('consultation_time')->default('00:50:00')->change();
            $table->time('available_time_minutes')->default('00:10:00')->change();
        });
    }

    public function down(): void
    {
        Schema::table('agenda_settings', function (Blueprint $table) {
            $table->time('consultation_time')->default('01:00:00')->change();
            $table->time('available_time_minutes')->default('00:30:00')->change();
        });
    }
};
