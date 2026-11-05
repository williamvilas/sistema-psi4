<?php

declare(strict_types=1);

use App\Enums\DocumentsEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('title');
            $table->enum('type', DocumentsEnum::values())->index();
            $table->string('path');
            $table->tinyInteger('active')->index()->default(0);
            $table->timestamps();
            $table->softDeletes()->index();
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
