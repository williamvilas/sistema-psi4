<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_events', function (Blueprint $table) {
            $table->id();
            $table->char('visitor_id', 36);
            $table->string('tag', 100);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('path', 255);
            $table->string('referrer', 255)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['visitor_id', 'tag']);
            $table->index(['tag', 'created_at']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_events');
    }
};
