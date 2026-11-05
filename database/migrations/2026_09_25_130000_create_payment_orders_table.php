<?php

declare(strict_types=1);

use App\Enums\PaymentOrderStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('agenda_id')->nullable()->unique();
            $table->decimal('amount', 10);
            $table->string('description')->nullable();
            $table->enum('status', PaymentOrderStatusEnum::values())->default(PaymentOrderStatusEnum::PENDING->value)->index();
            $table->json('payment_methods');
            $table->string('payment_method', 20)->nullable();
            $table->string('external_id')->nullable()->index();
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('patient_id')->references('id')->on('patients');
            $table->foreign('agenda_id')->references('id')->on('agenda');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
    }
};
