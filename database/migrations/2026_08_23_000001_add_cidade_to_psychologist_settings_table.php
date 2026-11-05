<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('psychologist_settings', function (Blueprint $table) {
            $table->string('cidade')->nullable()->after('cep');
        });
    }

    public function down(): void
    {
        Schema::table('psychologist_settings', function (Blueprint $table) {
            $table->dropColumn('cidade');
        });
    }
};
