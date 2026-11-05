<?php

declare(strict_types=1);

use App\Enums\WebhookStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook', function (Blueprint $table) {
            $table->id();
            $table->char('document', 14)->index()->nullable();
            $table->char('phone', 12)->index()->nullable();
            $table->char('email', 200)->index()->nullable();
            $table->enum('status', WebhookStatusEnum::values());
            $table->json('payload');
            $table->timestamps();
            $table->softDeletes()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook');
    }
};
