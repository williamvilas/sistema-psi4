<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Trava de idempotência (agenda + destinatário + tipo + canal). Vira null quando a consulta
            // é remarcada ou o horário cancelado é reaproveitado, liberando novos avisos sem perder o histórico.
            $table->string('dedupe_key', 100)->nullable()->unique()->after('channel');
            // Id da mensagem na API do WhatsApp (wamid), usado pelo webhook de status e respostas
            $table->string('provider_message_id', 100)->nullable()->index()->after('dedupe_key');
        });

        DB::table('notifications')
            ->whereNotNull('agenda_id')
            ->update(['dedupe_key' => DB::raw("CONCAT(agenda_id, ':', recipient, ':', type, ':', channel)")]);

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_agenda_recipient_type_channel_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->unique(['agenda_id', 'recipient', 'type', 'channel'], 'notifications_agenda_recipient_type_channel_unique');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique(['dedupe_key']);
            $table->dropIndex(['provider_message_id']);
            $table->dropColumn(['dedupe_key', 'provider_message_id']);
        });
    }
};
