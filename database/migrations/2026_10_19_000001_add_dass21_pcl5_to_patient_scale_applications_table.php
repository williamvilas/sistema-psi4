<?php

declare(strict_types=1);

use App\Enums\ScaleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_scale_applications', function (Blueprint $table) {
            $table->enum('scale', ScaleEnum::values())->change();
        });
    }

    public function down(): void
    {
        Schema::table('patient_scale_applications', function (Blueprint $table) {
            $table->enum('scale', ['gad7', 'phq9'])->change();
        });
    }
};
