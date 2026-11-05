<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psychologist_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name', 100);
            $table->decimal('price', 10)->default(0);
            $table->timestamps();
            $table->softDeletes()->index();
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::table('agenda', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable()->index()->after('patient_id');
            $table->foreign('service_id')->references('id')->on('psychologist_services');
        });
    }

    public function down(): void
    {
        Schema::table('agenda', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropColumn('service_id');
        });

        Schema::dropIfExists('psychologist_services');
    }
};
