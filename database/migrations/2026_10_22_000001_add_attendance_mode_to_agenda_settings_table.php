<?php

declare(strict_types=1);

use App\Enums\AttendanceModeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_settings', function (Blueprint $table) {
            $table->string('attendance_mode', 20)->default(AttendanceModeEnum::ONLINE->value)->after('available_time_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('agenda_settings', function (Blueprint $table) {
            $table->dropColumn('attendance_mode');
        });
    }
};
