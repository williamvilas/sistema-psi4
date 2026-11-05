<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_settings', function (Blueprint $table) {
            // Antecedência (minutos) do lembrete por WhatsApp; null desliga o lembrete da modalidade
            $table->unsignedSmallInteger('reminder_in_person_minutes')->nullable()->default(1440)->after('send_link_message');
            $table->unsignedSmallInteger('reminder_online_minutes')->nullable()->default(120)->after('reminder_in_person_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('agenda_settings', function (Blueprint $table) {
            $table->dropColumn(['reminder_in_person_minutes', 'reminder_online_minutes']);
        });
    }
};
