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
        Schema::create('user_document_defaults', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('type', DocumentsEnum::values());
            $table->string('field_key', 30);
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'type', 'field_key']);
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_document_defaults');
    }
};
