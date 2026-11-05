<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name')->index();
            $table->char('birthday_md', 5)->nullable()->index(); // MM-DD
            $table->date('birth_date')->nullable()->index();
            $table->char('document', 12)->index()->nullable();
            $table->char('phone', 12)->index();
            $table->string('email')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes()->index();
            $table->unique(['user_id', 'phone']);
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
