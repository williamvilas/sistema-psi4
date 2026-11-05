<?php

declare(strict_types=1);

use App\Enums\AccessLogActionEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->enum('action', AccessLogActionEnum::values())->change();
        });
    }

    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->enum('action', array_values(array_diff(AccessLogActionEnum::values(), ['medical_record_exported'])))->change();
        });
    }
};
