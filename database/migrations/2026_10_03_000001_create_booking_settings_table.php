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
        Schema::create('booking_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('public_booking_enabled')->default(false)->index();
            $table->timestamps();
        });

        $now = now();

        DB::table('psychologist_settings')->orderBy('id')->each(function ($row) use ($now) {
            DB::table('booking_settings')->insert([
                'user_id' => $row->user_id,
                'public_booking_enabled' => $row->public_booking_enabled,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_settings');
    }
};
