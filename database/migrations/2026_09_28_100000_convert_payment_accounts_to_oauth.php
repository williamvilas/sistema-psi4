<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Converte `payment_accounts` do modelo antigo (chave/token colados manualmente
     * e integração via API payments.local) para o modelo Mercado Pago Connect
     * (OAuth): a conta passa a guardar o token de acesso e o refresh token
     * devolvidos pelo Mercado Pago para a conta conectada do terapeuta.
     *
     * `external_uuid` (identificador do payments.local) não é mais usado.
     */
    public function up(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->string('public_key')->nullable()->change();
            $table->text('access_token')->nullable()->change();
            $table->text('refresh_token')->nullable()->after('access_token');
            $table->unsignedBigInteger('mp_user_id')->nullable()->after('refresh_token')->index();
            $table->string('scope')->nullable()->after('mp_user_id');
            $table->timestamp('token_expires_at')->nullable()->after('scope');
            $table->dropColumn('external_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->char('external_uuid', 36)->nullable()->after('status');
            $table->dropColumn(['refresh_token', 'mp_user_id', 'scope', 'token_expires_at']);
            $table->string('public_key')->nullable(false)->change();
            $table->text('access_token')->nullable(false)->change();
        });
    }
};
