<?php

declare(strict_types=1);

use App\Enums\PaymentOrderIntegrationStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->char('uuid', 36)->nullable()->unique()->after('id');
            $table->string('integration_status', 20)
                ->default(PaymentOrderIntegrationStatusEnum::PENDING->value)
                ->index()
                ->after('status');
        });

        DB::table('payment_orders')->whereNull('uuid')->chunkById(500, function ($orders) {
            foreach ($orders as $order) {
                DB::table('payment_orders')->where('id', $order->id)->update(['uuid' => Str::uuid()->toString()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropIndex(['integration_status']);
            $table->dropColumn(['uuid', 'integration_status']);
        });
    }
};
