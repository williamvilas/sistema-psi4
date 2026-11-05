<?php

declare(strict_types=1);

use App\Enums\SessionTranscriptionStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A transcrição passa a rodar em lote depois da sessão: entre o fim da gravação e o rascunho, ela fica em processamento.
return new class extends Migration
{
    private const array ADDED = [
        SessionTranscriptionStatusEnum::PROCESSING,
        SessionTranscriptionStatusEnum::FAILED,
    ];

    public function up(): void
    {
        Schema::table('session_transcriptions', function (Blueprint $table) {
            $table->enum('status', SessionTranscriptionStatusEnum::values())->change();
        });
    }

    public function down(): void
    {
        $added = array_map(fn (SessionTranscriptionStatusEnum $status) => $status->value, self::ADDED);

        DB::table('session_transcriptions')->whereIn('status', $added)->update(['status' => SessionTranscriptionStatusEnum::DISCARDED->value]);

        Schema::table('session_transcriptions', function (Blueprint $table) use ($added) {
            $table->enum('status', array_values(array_diff(SessionTranscriptionStatusEnum::values(), $added)))->change();
        });
    }
};
