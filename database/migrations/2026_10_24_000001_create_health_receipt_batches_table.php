<?php

declare(strict_types=1);

use App\Enums\HealthReceiptBatchStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_receipt_batches', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('receipts_count');
            $table->decimal('total_amount', 12);
            $table->enum('status', HealthReceiptBatchStatusEnum::values())->default(HealthReceiptBatchStatusEnum::GENERATED->value);
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_receipt_batches');
    }
};
