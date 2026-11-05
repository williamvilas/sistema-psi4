<?php

declare(strict_types=1);

use App\Enums\ScaleEncryptionEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_scale_applications', function (Blueprint $table) {
            // symmetric: aplicada pela psicóloga (chave dos prontuários); ecdh: respondida pelo paciente por link.
            $table->enum('encryption', ScaleEncryptionEnum::values())->default(ScaleEncryptionEnum::SYMMETRIC->value)->after('content_path');
        });
    }

    public function down(): void
    {
        Schema::table('patient_scale_applications', function (Blueprint $table) {
            $table->dropColumn('encryption');
        });
    }
};
