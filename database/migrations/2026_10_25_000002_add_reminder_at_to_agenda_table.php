<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda', function (Blueprint $table) {
            // Momento (UTC) do lembrete por WhatsApp; volta a null depois de registrado
            $table->timestamp('reminder_at')->nullable()->after('ends_at')->index();
            $table->index(['type', 'status', 'scheduled_on'], 'agenda_type_status_scheduled_on_index');
        });
    }

    public function down(): void
    {
        Schema::table('agenda', function (Blueprint $table) {
            $table->dropIndex('agenda_type_status_scheduled_on_index');
            $table->dropColumn('reminder_at');
        });
    }
};
