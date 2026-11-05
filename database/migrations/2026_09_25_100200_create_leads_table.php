<?php

declare(strict_types=1);

use App\Enums\LeadsStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name')->index();
            $table->date('date_of_birth')->nullable()->index();
            $table->string('phone')->index();
            $table->string('email')->nullable()->index();
            $table->enum('status', LeadsStatusEnum::values())->index();
            $table->date('next_follow_up')->nullable()->index();
            $table->timestamps();
            $table->softDeletes()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
