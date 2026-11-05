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
        Schema::table('ai_token_balances', function (Blueprint $table) {
            $table->uuid('user_uuid')->nullable()->after('id');
        });

        DB::statement('UPDATE ai_token_balances b JOIN users u ON u.id = b.user_id SET b.user_uuid = u.uuid');

        Schema::table('ai_token_balances', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('ai_token_balances', function (Blueprint $table) {
            $table->uuid('user_uuid')->nullable(false)->change();
            $table->unique('user_uuid');
            $table->foreign('user_uuid')->references('uuid')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_token_balances', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id');
        });

        DB::statement('UPDATE ai_token_balances b JOIN users u ON u.uuid = b.user_uuid SET b.user_id = u.id');

        Schema::table('ai_token_balances', function (Blueprint $table) {
            $table->dropForeign(['user_uuid']);
            $table->dropUnique(['user_uuid']);
            $table->dropColumn('user_uuid');
        });

        Schema::table('ai_token_balances', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
