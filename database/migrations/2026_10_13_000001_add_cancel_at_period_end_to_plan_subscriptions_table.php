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
            // Quando o usuário cancelou a renovação: o plano segue ativo até `users.plan_expiration_date`
            $table->timestamp('cancel_at_period_end_at')->nullable()->after('renewal_notified_days');
        });
    }

    public function down(): void
    {
        Schema::table('plan_subscriptions', function (Blueprint $table) {
            $table->dropColumn('cancel_at_period_end_at');
        });
    }
};
