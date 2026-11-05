<?php

declare(strict_types=1);

use App\Enums\HealthReceiptSourceEnum;
use App\Enums\HealthReceiptStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_receipts', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('patient_id')->index();
            $table->unsignedBigInteger('payment_order_id')->nullable()->unique();
            $table->unsignedBigInteger('health_receipt_batch_id')->nullable()->index();
            $table->enum('source', HealthReceiptSourceEnum::values());
            $table->decimal('amount', 10);
            $table->date('paid_on');
            $table->string('description')->nullable();
            // CPFs gravados no momento da exportação: o arquivo baixado de novo sai idêntico ao enviado
            $table->char('payer_document', 11)->nullable();
            $table->char('beneficiary_document', 11)->nullable();
            $table->enum('status', HealthReceiptStatusEnum::values())->default(HealthReceiptStatusEnum::PENDING->value);
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status', 'paid_on']);
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('patient_id')->references('id')->on('patients');
            $table->foreign('payment_order_id')->references('id')->on('payment_orders');
            $table->foreign('health_receipt_batch_id')->references('id')->on('health_receipt_batches');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_receipts');
    }
};
