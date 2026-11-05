<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link de cobrança (`init_point` da preference do Checkout Pro) gerado na
     * conta Mercado Pago conectada do terapeuta, para ser enviado ao paciente.
     */
    public function up(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->text('checkout_url')->nullable()->after('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropColumn('checkout_url');
        });
    }
};
