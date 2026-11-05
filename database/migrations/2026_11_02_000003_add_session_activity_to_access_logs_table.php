<?php

declare(strict_types=1);

use App\Enums\AccessLogActionEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array ACTIONS = [
        AccessLogActionEnum::SESSION_ACTIVITY_CREATED,
        AccessLogActionEnum::SESSION_ACTIVITY_VIEWED,
        AccessLogActionEnum::SESSION_ACTIVITY_PIN_FAILED,
        AccessLogActionEnum::SESSION_ACTIVITY_PIN_CHANGED,
    ];

    public function up(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->enum('action', AccessLogActionEnum::values())->change();
        });
    }

    public function down(): void
    {
        $added = array_map(fn (AccessLogActionEnum $action) => $action->value, self::ACTIONS);

        Schema::table('access_logs', function (Blueprint $table) use ($added) {
            $table->enum('action', array_values(array_diff(AccessLogActionEnum::values(), $added)))->change();
        });
    }
};
