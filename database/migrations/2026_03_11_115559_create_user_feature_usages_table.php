<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_feature_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->char('feature', 100)->index(); // ex: schedule_create, pdf_generate, report_export
            $table->integer('year')->index();
            $table->integer('month')->index();
            $table->integer('usage_count')->default(0);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users');
            $table->unique(['user_id', 'feature', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_feature_usages');
    }
};
