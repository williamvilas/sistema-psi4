<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_subscriptions', function (Blueprint $table) {
            $table->string('cycle', 10)->default('monthly')->after('plan');
            $table->string('payment_method', 10)->nullable()->after('cycle');
            $table->string('payment_id')->nullable()->unique()->after('preapproval_id');
            // Último marco de aviso de renovação enviado (15, 3 ou 0 dias antes do vencimento)
            $table->unsignedSmallInteger('renewal_notified_days')->nullable()->after('init_point');
        });
    }

    public function down(): void
    {
        Schema::table('plan_subscriptions', function (Blueprint $table) {
            $table->dropUnique(['payment_id']);
            $table->dropColumn(['cycle', 'payment_method', 'payment_id', 'renewal_notified_days']);
        });
    }
};
