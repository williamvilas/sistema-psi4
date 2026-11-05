<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assinatura do plano SaaS da própria PSI4 via Mercado Pago (`/preapproval`),
     * substituindo a integração com Kiwify. Cada linha reconcilia um
     * `preapproval_id`/`external_reference` do Mercado Pago com o usuário e o
     * plano (`App\Enums\PlanUserEnum`) contratado.
     */
    public function up(): void
    {
        Schema::create('plan_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 20);
            $table->char('external_reference', 36)->unique();
            $table->string('preapproval_id')->nullable()->unique();
            $table->string('status', 20)->index();
            $table->text('init_point')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_subscriptions');
    }
};
