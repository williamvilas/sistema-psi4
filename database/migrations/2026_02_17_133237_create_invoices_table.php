<?php

declare(strict_types=1);

use App\Enums\InvoicesStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->char('order_id')->index();
            $table->enum('status', InvoicesStatusEnum::values())->index();
            $table->decimal('amount', 10);
            $table->decimal('net_amount', 10)->nullable();
            $table->decimal('gateway_fee', 10)->nullable();
            $table->decimal('tax', 10)->nullable();
            $table->json('payload');
            $table->timestamps();
            $table->softDeletes()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
