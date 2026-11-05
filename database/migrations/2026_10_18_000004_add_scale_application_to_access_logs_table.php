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
            $table->unsignedBigInteger('scale_application_id')->nullable()->after('medical_record_id');
            $table->index(['user_id', 'scale_application_id']);
        });
    }

    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'scale_application_id']);
            $table->dropColumn('scale_application_id');
            $table->enum('action', array_values(array_filter(AccessLogActionEnum::values(), fn ($value) => ! str_starts_with($value, 'scale_application_'))))->change();
        });
    }
};
