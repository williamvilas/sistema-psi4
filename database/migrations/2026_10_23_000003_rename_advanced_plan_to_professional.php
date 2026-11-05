<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // O plano Avançado virou Profissional e o Intermediário deixou de existir
        DB::statement("ALTER TABLE users MODIFY current_plan ENUM('free', 'intermediary', 'advanced', 'professional') NOT NULL DEFAULT 'free'");

        DB::table('users')->where('current_plan', 'advanced')->update(['current_plan' => 'professional']);
        DB::table('users')->where('current_plan', 'intermediary')->update(['current_plan' => 'free']);
        DB::table('plan_subscriptions')->where('plan', 'advanced')->update(['plan' => 'professional']);

        DB::statement("ALTER TABLE users MODIFY current_plan ENUM('free', 'professional') NOT NULL DEFAULT 'free'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY current_plan ENUM('free', 'intermediary', 'advanced', 'professional') NOT NULL DEFAULT 'free'");

        DB::table('users')->where('current_plan', 'professional')->update(['current_plan' => 'advanced']);
        DB::table('plan_subscriptions')->where('plan', 'professional')->update(['plan' => 'advanced']);

        DB::statement("ALTER TABLE users MODIFY current_plan ENUM('free', 'intermediary', 'advanced') NOT NULL DEFAULT 'free'");
    }
};
