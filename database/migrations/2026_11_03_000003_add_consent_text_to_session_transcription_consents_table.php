<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_transcription_consents', function (Blueprint $table) {
            // O texto que a pessoa leu: a prova continua de pé depois que o texto em vigor mudar.
            $table->text('consent_text')->nullable()->after('consent_hash');
        });
    }

    public function down(): void
    {
        Schema::table('session_transcription_consents', function (Blueprint $table) {
            $table->dropColumn('consent_text');
        });
    }
};
