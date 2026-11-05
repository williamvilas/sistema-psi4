<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropIndex(['evolution']);
            $table->dropColumn('evolution');
        });
    }

    // Os valores apagados não voltam: a evolução passou a ser acompanhada pelas escalas cifradas.
    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->enum('evolution', ['slight_improvement', 'significant_improvement', 'stable', 'slight_worsening', 'significant_worsening'])->nullable()->index()->after('title');
        });
    }
};
