<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('agenda_id')->nullable()->after('patient_id')->index();
            $table->string('recipient', 20)->after('agenda_id')->default('patient')->index();
            $table->string('channel', 20)->after('type')->index();
            $table->text('error')->nullable()->after('message');
            $table->foreign('agenda_id')->references('id')->on('agenda');
            $table->unique(['agenda_id', 'recipient', 'type', 'channel'], 'notifications_agenda_recipient_type_channel_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_agenda_recipient_type_channel_unique');
            $table->dropForeign(['agenda_id']);
            $table->dropColumn(['agenda_id', 'recipient', 'channel', 'error']);
        });
    }
};
