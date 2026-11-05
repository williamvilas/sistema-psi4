<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Avisos automáticos por WhatsApp; desligado pela psicóloga ou pelo paciente (responder SAIR)
            $table->boolean('whatsapp_notifications')->default(true)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('whatsapp_notifications');
        });
    }
};
