<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'paid_at'], 'payment_orders_user_status_paid_at_index');
            $table->index(['user_id', 'status', 'due_date'], 'payment_orders_user_status_due_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropIndex('payment_orders_user_status_paid_at_index');
            $table->dropIndex('payment_orders_user_status_due_date_index');
        });
    }
};
