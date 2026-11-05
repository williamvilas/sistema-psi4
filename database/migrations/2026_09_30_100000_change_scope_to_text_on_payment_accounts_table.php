<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O Mercado Pago retorna um `scope` com todas as permissões concedidas
     * pela conta conectada (string longa, com múltiplos escopos separados por
     * espaço) — facilmente ultrapassa os 255 caracteres do `string` original,
     * causando `Data too long for column 'scope'` ao salvar o `PaymentAccount`.
     */
    public function up(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->text('scope')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->string('scope')->nullable()->change();
        });
    }
};
