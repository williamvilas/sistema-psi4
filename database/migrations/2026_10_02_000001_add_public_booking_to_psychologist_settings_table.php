<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('psychologist_settings', function (Blueprint $table) {
            $table->boolean('public_booking_enabled')->default(false)->index()->after('holidays');
        });
    }

    public function down(): void
    {
        Schema::table('psychologist_settings', function (Blueprint $table) {
            $table->dropIndex(['public_booking_enabled']);
            $table->dropColumn('public_booking_enabled');
        });
    }
};
